<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('connexion', 'auth.connexion')->name('login');

    // Anciennes adresses du starter kit : tout passe par l'identification « email d'abord ».
    Route::redirect('login', 'connexion');
    Route::redirect('register', 'connexion')->name('register');
    Route::redirect('forgot-password', 'connexion')->name('password.request');
});

Route::middleware('auth')->group(function () {
    Route::view('verification-email', 'auth.verification')->name('verification.notice');
});

Route::post('deconnexion', Logout::class)->name('logout');
