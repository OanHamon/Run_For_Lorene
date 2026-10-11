<?php

use App\Http\Controllers\Admin\JournalController;
use App\Http\Controllers\Admin\TableauController;
use Illuminate\Support\Facades\Route;

/*
 * Back-office (préfixe /admin, noms admin.*), réservé aux admins : niveau 1 en lecture,
 * `admin:2` pour gérer inscriptions et documents, `admin:3` pour le paramétrage,
 * les remboursements et les admins.
 */

Route::get('/', TableauController::class)->name('tableau');
Route::get('journal', JournalController::class)->name('journal');
