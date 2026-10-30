<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ConfirmationAdmin;
use App\Models\User;
use App\Services\Auth\CodeEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Connexion au back-office, séparée de l'espace participant (garde « admin ») :
 *
 * 1. email et mot de passe d'un compte admin ;
 * 2. code à 6 chiffres reçu par email (usage CodeEmail::ADMIN : un code de connexion
 *    participant ne vaut pas ici).
 *
 * Les deux étapes sont obligatoires, pas de « se souvenir de moi » ; la session dure ensuite
 * quelques heures (ConfirmationAdmin). Un compte inconnu, non admin ou au mauvais mot de passe
 * reçoit la même réponse. Envois et essais de code suivent les limites de CodeEmail.
 */
class ConnexionController extends Controller
{
    /** Compte dont le mot de passe vient d'être vérifié, en attente du code : [id_user, horodatage]. */
    private const EN_ATTENTE = 'admin.connexion_en_attente';

    public function __construct(private CodeEmail $codes) {}

    public function show(): View
    {
        return view('admin.connexion.show');
    }

    public function connecter(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $request->validate([
            'email' => ['required', 'email:rfc', 'max:254'],
            'mot_de_passe' => ['required', 'string'],
        ], [
            'email.required' => 'Saisis ton adresse email.',
            'email.email' => 'Cette adresse email n\'est pas valide.',
            'mot_de_passe.required' => 'Saisis ton mot de passe.',
        ]);

        // Limites par compte (quelle que soit l'adresse IP) et par adresse IP (quel que soit le compte).
        $cleCompte = 'admin-mdp:'.$request->input('email');
        $cleIp = 'admin-mdp-ip:'.$request->ip();
        foreach ([[$cleCompte, 5], [$cleIp, 20]] as [$cle, $max]) {
            if (RateLimiter::tooManyAttempts($cle, $max)) {
                return back()->onlyInput('email')
                    ->withErrors(['email' => 'Trop de tentatives. Réessaie dans '.RateLimiter::availableIn($cle).' secondes.']);
            }
        }

        $user = User::with('admin')->where('email', $request->input('email'))->first();

        if (! $user?->admin || ! $user->mot_de_passe || ! Hash::check($request->input('mot_de_passe'), $user->mot_de_passe)) {
            RateLimiter::hit($cleCompte, 60);
            RateLimiter::hit($cleIp, 60);

            return back()->onlyInput('email')->withErrors(['email' => 'Email ou mot de passe incorrect.']);
        }

        RateLimiter::clear($cleCompte);
        $request->session()->put(self::EN_ATTENTE, [$user->id_user, now()->getTimestamp()]);

        return redirect()->route('admin.connexion.code');
    }

    public function code(Request $request): View|RedirectResponse
    {
        $user = $this->enAttente($request);

        if (! $user) {
            return $this->recommencer();
        }

        $attente = $this->codes->aUnCodeActif($user, CodeEmail::ADMIN) ? null : $this->envoyer($user);

        return view('admin.connexion.code', ['email' => $user->email, 'attente' => $attente]);
    }

    public function valider(Request $request): RedirectResponse
    {
        $user = $this->enAttente($request);

        if (! $user) {
            return $this->recommencer();
        }

        $request->merge(['code' => preg_replace('/\D/', '', (string) $request->input('code'))]);
        $request->validate(['code' => 'required|digits:6'], [
            'code.required' => 'Saisis le code à 6 chiffres reçu par email.',
            'code.digits' => 'Le code contient 6 chiffres.',
        ]);

        // En plus des 5 essais par code : limite par adresse IP, comme à la connexion participant.
        $cleIp = 'code-ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($cleIp, 20)) {
            return back()->withErrors(['code' => 'Trop de tentatives. Réessaie dans '.RateLimiter::availableIn($cleIp).' secondes.']);
        }
        RateLimiter::hit($cleIp, 60);

        $resultat = $this->codes->verifier($user, $request->input('code'), CodeEmail::ADMIN);

        if ($resultat === 'invalide') {
            return back()->withErrors(['code' => 'Ce code ne correspond pas. Vérifie-le et réessaie.']);
        }

        if ($resultat === 'expire') {
            return back()->withErrors(['code' => 'Ce code a expiré. Demande-en un nouveau.']);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $request->session()->forget(self::EN_ATTENTE);
        Auth::guard('admin')->login($user);
        $request->session()->regenerate();
        $request->session()->put(ConfirmationAdmin::CLE, now()->getTimestamp());

        // Page demandée avant la connexion, si c'est une page de l'admin (pas de redirection ouverte).
        $demandee = (string) $request->session()->pull('url.intended');

        return redirect()->to(Str::startsWith($demandee, url('admin').'/') ? $demandee : route('admin.tableau'));
    }

    public function renvoyer(Request $request): RedirectResponse
    {
        $user = $this->enAttente($request);

        if (! $user) {
            return $this->recommencer();
        }

        $attente = $this->envoyer($user);

        return $attente
            ? back()->withErrors(['code' => $attente])
            : back()->with('statut', 'Nouveau code envoyé.');
    }

    public function deconnexion(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.connexion')->with('statut', 'Tu es déconnecté(e) de l\'administration.');
    }

    /** Le compte admin en attente de code, tant que le code envoyé peut encore être valable. */
    private function enAttente(Request $request): ?User
    {
        [$id, $le] = (array) $request->session()->get(self::EN_ATTENTE) + [null, 0];

        if (! $id || $le + CodeEmail::VALIDITE_MINUTES * 60 < now()->getTimestamp()) {
            return null;
        }

        $user = User::with('admin')->find($id);

        return $user?->admin ? $user : null;
    }

    private function recommencer(): RedirectResponse
    {
        session()->forget(self::EN_ATTENTE);

        return redirect()->route('admin.connexion')->with('statut', 'Saisis à nouveau ton email et ton mot de passe.');
    }

    /** Envoie un code ; null s'il est parti, sinon le message d'attente à afficher. */
    private function envoyer(User $user): ?string
    {
        if ($this->codes->envoyer($user, CodeEmail::ADMIN)) {
            return null;
        }

        $attente = $this->codes->secondesAvantRenvoi($user, CodeEmail::ADMIN);

        return $this->codes->aUnCodeActif($user, CodeEmail::ADMIN)
            ? "Patiente encore {$attente} s avant de redemander un code."
            : 'Trop de codes demandés. Réessaie dans '.(int) ceil($attente / 60).' min.';
    }
}
