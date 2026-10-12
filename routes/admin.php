<?php

use App\Http\Controllers\Admin\CommandeController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\DonController;
use App\Http\Controllers\Admin\EvenementController;
use App\Http\Controllers\Admin\InscriptionController;
use App\Http\Controllers\Admin\InscriptionGratuiteController;
use App\Http\Controllers\Admin\JournalController;
use App\Http\Controllers\Admin\OptionController;
use App\Http\Controllers\Admin\QuestionnaireController;
use App\Http\Controllers\Admin\TableauController;
use App\Http\Controllers\Admin\TarifController;
use App\Http\Controllers\Admin\TexteLegalController;
use App\Http\Controllers\Admin\UtilisateurController;
use Illuminate\Support\Facades\Route;

/*
 * Back-office (préfixe /admin, noms admin.*), réservé aux admins : niveau 1 en lecture,
 * `admin:2` pour gérer inscriptions et documents, `admin:3` pour le paramétrage,
 * les remboursements et les admins.
 */

Route::get('/', TableauController::class)->name('tableau');
Route::get('journal', JournalController::class)->name('journal');

// Suivi des inscriptions : consultation et exports pour tous les admins, actions au niveau 2.
Route::get('inscriptions', [InscriptionController::class, 'index'])->name('inscriptions.index');
Route::get('inscriptions/export', [InscriptionController::class, 'export'])->name('inscriptions.export');
Route::get('inscriptions/dossards', [InscriptionController::class, 'exportDossards'])->name('inscriptions.dossards');
Route::get('inscriptions/{inscription}', [InscriptionController::class, 'show'])->whereNumber('inscription')->name('inscriptions.show');

Route::middleware('admin:2')->group(function () {
    // Questionnaires de santé : réponses visibles des seuls niveaux habilités.
    Route::get('questionnaires', [QuestionnaireController::class, 'index'])->name('questionnaires.index');
    Route::get('questionnaires/{document}', [QuestionnaireController::class, 'show'])->name('questionnaires.show');
    Route::post('questionnaires/{document}/verifier', [QuestionnaireController::class, 'verifier'])->name('questionnaires.verifier');

    Route::get('inscriptions/gratuite', [InscriptionGratuiteController::class, 'create'])->name('inscriptions.create');
    Route::post('inscriptions/gratuite', [InscriptionGratuiteController::class, 'store'])->name('inscriptions.store');
    Route::put('inscriptions/{inscription}/dossard', [InscriptionController::class, 'dossard'])->name('inscriptions.dossard');
    Route::post('inscriptions/{inscription}/dossard', [InscriptionController::class, 'attribuerDossard'])->name('inscriptions.attribuer-dossard');
    Route::post('inscriptions/{inscription}/annuler', [InscriptionController::class, 'annuler'])->name('inscriptions.annuler');
    Route::post('inscriptions/{inscription}/relancer', [InscriptionController::class, 'relancer'])->name('inscriptions.relancer');
});

// Commandes et paiements : consultation pour tous, remboursements au niveau 3.
Route::get('commandes', [CommandeController::class, 'index'])->name('commandes.index');
Route::get('commandes/{commande}', [CommandeController::class, 'show'])->name('commandes.show');
Route::post('paiements/{paiement}/rembourser', [CommandeController::class, 'rembourser'])->middleware('admin:3')->name('paiements.rembourser');

Route::get('dons', [DonController::class, 'index'])->name('dons.index');
Route::get('dons/export', [DonController::class, 'export'])->name('dons.export');

// Paramétrage des événements (lot 2) : consultation pour tous les admins…
Route::get('evenements', [EvenementController::class, 'index'])->name('evenements.index');
Route::get('evenements/{evenement}', [EvenementController::class, 'show'])->whereNumber('evenement')->name('evenements.show');
Route::get('courses/{course}', [CourseController::class, 'edit'])->name('courses.edit');
Route::get('tarifs/{tarif}', [TarifController::class, 'edit'])->name('tarifs.edit');
Route::get('options/{option}', [OptionController::class, 'edit'])->name('options.edit');
Route::get('textes/{texte}', [TexteLegalController::class, 'show'])->name('textes.show');

// … modification réservée aux super admins.
Route::middleware('admin:3')->group(function () {
    Route::get('evenements/nouveau', [EvenementController::class, 'create'])->name('evenements.create');
    Route::post('evenements', [EvenementController::class, 'store'])->name('evenements.store');
    Route::put('evenements/{evenement}', [EvenementController::class, 'update'])->name('evenements.update');
    Route::post('evenements/{evenement}/dupliquer', [EvenementController::class, 'dupliquer'])->name('evenements.dupliquer');

    Route::get('evenements/{evenement}/courses/nouvelle', [CourseController::class, 'create'])->name('courses.create');
    Route::post('evenements/{evenement}/courses', [CourseController::class, 'store'])->name('courses.store');
    Route::put('courses/{course}', [CourseController::class, 'update'])->name('courses.update');
    Route::delete('courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');

    Route::post('courses/{course}/tarifs', [TarifController::class, 'store'])->name('tarifs.store');
    Route::put('tarifs/{tarif}', [TarifController::class, 'update'])->name('tarifs.update');
    Route::delete('tarifs/{tarif}', [TarifController::class, 'destroy'])->name('tarifs.destroy');

    Route::get('evenements/{evenement}/options/nouvelle', [OptionController::class, 'create'])->name('options.create');
    Route::post('evenements/{evenement}/options', [OptionController::class, 'store'])->name('options.store');
    Route::put('options/{option}', [OptionController::class, 'update'])->name('options.update');
    Route::delete('options/{option}', [OptionController::class, 'destroy'])->name('options.destroy');
    Route::post('options/{option}/declinaisons', [OptionController::class, 'ajouterDeclinaison'])->name('declinaisons.store');
    Route::put('declinaisons/{declinaison}', [OptionController::class, 'modifierDeclinaison'])->name('declinaisons.update');
    Route::delete('declinaisons/{declinaison}', [OptionController::class, 'supprimerDeclinaison'])->name('declinaisons.destroy');

    Route::post('evenements/{evenement}/textes', [TexteLegalController::class, 'store'])->name('textes.store');

    Route::get('utilisateurs', [UtilisateurController::class, 'index'])->name('utilisateurs.index');
    Route::get('utilisateurs/{utilisateur}', [UtilisateurController::class, 'show'])->name('utilisateurs.show');
    Route::put('utilisateurs/{utilisateur}/niveau', [UtilisateurController::class, 'niveau'])->name('utilisateurs.niveau');
    Route::post('utilisateurs/{utilisateur}/reinitialiser', [UtilisateurController::class, 'reinitialiser'])->name('utilisateurs.reinitialiser');
});
