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
 * Lien expiré, modifié ou déjà utilisé : page d'explication, connexion par code en secours.
 */
class ActivationController extends Controller
{
    public function show(Request $request, User $user): View
    {
        if (! $this->lienValable($request, $user)) {
            return view('auth.activation-invalide', ['dejaActive' => $user->mot_de_passe !== null && $request->hasValidSignature()]);
        }

        return view('auth.activation', ['user' => $user]);
    }

    public function store(Request $request, User $user): RedirectResponse|View
    {
        if (! $this->lienValable($request, $user)) {
            return view('auth.activation-invalide', ['dejaActive' => $user->mot_de_passe !== null]);
        }

        $request->validate([
            'mot_de_passe' => ['required', 'string', Password::min(8), 'confirmed'],
        ], [
            'mot_de_passe.required' => 'Choisis un mot de passe.',
            'mot_de_passe.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'mot_de_passe.confirmed' => 'Les deux mots de passe ne sont pas identiques.',
        ]);

        $user->forceFill([
            'mot_de_passe' => Hash::make($request->input('mot_de_passe')),
            'remember_token' => Str::random(60), // les autres liens envoyés deviennent caducs
        ])->save();

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        Auth::login($user, remember: true);
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
