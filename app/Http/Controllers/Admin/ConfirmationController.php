<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ConfirmationAdmin;
use App\Services\Auth\CodeEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Page « confirme ton accès » : code à 6 chiffres envoyé par email à chaque entrée dans
 * l'admin (voir ConfirmationAdmin). Envois et essais suivent les limites de CodeEmail.
 */
class ConfirmationController extends Controller
{
    public function __construct(private CodeEmail $codes) {}

    public function show(Request $request): View|RedirectResponse
    {
        if (ConfirmationAdmin::estConfirmee($request)) {
            return $this->suite($request);
        }

        $user = $request->user();
        $attente = $this->codes->aUnCodeActif($user) ? null : $this->envoyer($request);

        return view('admin.confirmation.show', ['email' => $user->email, 'attente' => $attente]);
    }

    public function valider(Request $request): RedirectResponse
    {
        $request->merge(['code' => preg_replace('/\D/', '', (string) $request->input('code'))]);
        $request->validate(['code' => 'required|digits:6'], [
            'code.required' => 'Saisis le code à 6 chiffres reçu par email.',
            'code.digits' => 'Le code contient 6 chiffres.',
        ]);

        // En plus des 5 essais par code : limite par adresse IP, comme à la connexion.
        $cleIp = 'code-ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($cleIp, 20)) {
            return back()->withErrors(['code' => 'Trop de tentatives. Réessaie dans '.RateLimiter::availableIn($cleIp).' secondes.']);
        }
        RateLimiter::hit($cleIp, 60);

        $resultat = $this->codes->verifier($request->user(), $request->input('code'));

        if ($resultat === 'invalide') {
            return back()->withErrors(['code' => 'Ce code ne correspond pas. Vérifie-le et réessaie.']);
        }

        if ($resultat === 'expire') {
            return back()->withErrors(['code' => 'Ce code a expiré. Demande-en un nouveau.']);
        }

        $request->session()->regenerate();
        $request->session()->put(ConfirmationAdmin::CLE, now()->getTimestamp());

        return $this->suite($request);
    }

    public function renvoyer(Request $request): RedirectResponse
    {
        $attente = $this->envoyer($request);

        return $attente
            ? back()->withErrors(['code' => $attente])
            : back()->with('statut', 'Nouveau code envoyé.');
    }

    /** Envoie un code ; null s'il est parti, sinon le message d'attente à afficher. */
    private function envoyer(Request $request): ?string
    {
        $user = $request->user();

        if ($this->codes->envoyer($user)) {
            return null;
        }

        $attente = $this->codes->secondesAvantRenvoi($user);

        return $this->codes->aUnCodeActif($user)
            ? "Patiente encore {$attente} s avant de redemander un code."
            : 'Trop de codes demandés. Réessaie dans '.(int) ceil($attente / 60).' min.';
    }

    private function suite(Request $request): RedirectResponse
    {
        return redirect()->to($request->session()->pull(ConfirmationAdmin::CLE_URL, route('admin.tableau')));
    }
}
