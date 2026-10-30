<?php

use App\Http\Controllers\Admin\ConnexionController;
use Illuminate\Support\Facades\Route;

/*
 * Connexion au back-office (préfixe /admin, noms admin.*), séparée de celle des participants :
 * email et mot de passe, puis code à 6 chiffres reçu par email. Seuls les comptes admin passent.
 */

Route::middleware(['guest:admin', 'throttle:30,1'])->group(function () {
    Route::get('connexion', [ConnexionController::class, 'show'])->name('connexion');
    Route::post('connexion', [ConnexionController::class, 'connecter'])->name('connexion.connecter');
    Route::get('connexion/code', [ConnexionController::class, 'code'])->name('connexion.code');
    Route::post('connexion/code', [ConnexionController::class, 'valider'])->name('connexion.valider');
    Route::post('connexion/code/renvoyer', [ConnexionController::class, 'renvoyer'])->name('connexion.renvoyer');
});

Route::post('deconnexion', [ConnexionController::class, 'deconnexion'])->name('deconnexion');
