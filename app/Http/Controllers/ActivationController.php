<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Activation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Activation d'un compte créé par l'association : depuis le lien reçu par email, la personne
 * choisit son mot de passe ; son email est alors vérifié (le lien le prouve) et elle est
 * connectée, puis envoyée vers la page prévue (son inscription, son espace).
 * Un compte admin n'est pas connecté : il passe par la connexion admin (mot de passe et code).
 * Lien expiré, modifié ou déjà utilisé : page d'explication, connexion par code en secours.
 */
class ActivationController extends Controller
{
    /*
     * Le compte est cherché à la main, sans liaison de route : un identifiant inconnu donne la
     * même page « lien invalide » qu'un lien faux, et ne révèle pas quels comptes existent.
     */

    public function show(Request $request, string $user): View
    {
        $user = User::find((int) $user);

        if (! $user || ! $this->lienValable($request, $user)) {
            return view('auth.activation-invalide', ['dejaActive' => $user?->mot_de_passe !== null && $request->hasValidSignature()]);
        }

        return view('auth.activation', ['user' => $user]);
    }

    public function store(Request $request, string $user): RedirectResponse|View
    {
        $user = User::find((int) $user);

        if (! $user || ! $this->lienValable($request, $user)) {
            return view('auth.activation-invalide', ['dejaActive' => $user?->mot_de_passe !== null && $request->hasValidSignature()]);
        }

        $request->validate([
            'mot_de_passe' => ['required', 'string', Password::defaults(), 'confirmed'],
        ], [
            'mot_de_passe.required' => 'Choisis un mot de passe.',
            'mot_de_passe.min' => 'Le mot de passe doit contenir au moins '.\App\Support\MotDePasse::LONGUEUR_MIN.' caractères.',
            'mot_de_passe.confirmed' => 'Les deux mots de passe ne sont pas identiques.',
        ]);

        $user->forceFill([
            'mot_de_passe' => Hash::make($request->input('mot_de_passe')),
            'remember_token' => Str::random(60), // les autres liens envoyés deviennent caducs
        ])->save();

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        if ($user->admin) {
            return redirect()->route('admin.connexion')->withInput(['email' => $user->email])
                ->with('statut', 'Ton mot de passe est enregistré. Connecte-toi à l\'administration.');
        }

        Auth::guard('web')->login($user, remember: true);
        $request->session()->regenerate();
        $request->session()->put('auth.password_confirmed_at', time());

        return redirect(Activation::suite($request->query('suite')))
            ->with('statut', 'Ton mot de passe est enregistré : bienvenue dans ton espace !');
    }

    private function lienValable(Request $request, User $user): bool
    {
        return $request->hasValidSignature() && Activation::valable($user, $request->query('cle'));
    }
}
