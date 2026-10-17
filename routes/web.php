<?php

use App\Http\Controllers\AccueilController;
use App\Http\Controllers\ActivationController;
use App\Http\Controllers\EspaceController;
use App\Http\Controllers\PaiementController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TexteLegalController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', AccueilController::class)->name('home');
Route::get('textes/{type}', TexteLegalController::class)->name('textes.show');

// Compte créé par l'association : la personne choisit son mot de passe depuis le lien reçu par email.
Route::get('activation/{user}', [ActivationController::class, 'show'])->middleware('throttle:30,1')->name('compte.activation');
Route::post('activation/{user}', [ActivationController::class, 'store'])->middleware('throttle:10,1');

Volt::route('inscription', 'inscription.tunnel')->name('inscription.tunnel');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('inscription/commande/{commande}/paiement', [PaiementController::class, 'payer'])
        ->middleware('throttle:10,1')
        ->name('inscription.payer');
    Route::get('inscription/commande/{commande}/confirmation', [PaiementController::class, 'confirmation'])->name('inscription.confirmation');
    Route::get('inscription/commande/{commande}/paiement-non-abouti', [PaiementController::class, 'echec'])->name('inscription.echec');
    Route::post('inscription/commande/{commande}/modifier', [PaiementController::class, 'modifier'])->name('inscription.modifier');

    Route::get('paiement/simulation/{paiement}', [PaiementController::class, 'simulation'])->name('paiement.simulation');
    Route::post('paiement/simulation/{paiement}/{issue}', [PaiementController::class, 'simuler'])
        ->whereIn('issue', ['reussi', 'echec', 'abandon'])
        ->name('paiement.simuler');

    Route::get('espace', EspaceController::class)->name('espace');
    Volt::route('espace/inscriptions/{inscription}', 'espace.inscription')->name('espace.inscription');
    Volt::route('espace/compte', 'espace.compte')->name('espace.compte');
});

Route::post('stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

Route::redirect('dashboard', 'espace')->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
