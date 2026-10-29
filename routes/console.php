<?php

use App\Models\Evenement;
use App\Services\Inscriptions\PurgerDocuments;
use App\Services\Inscriptions\Relances;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Purge RGPD des documents 12 mois après l'événement (spec), lancée chaque nuit par le planificateur.
Artisan::command('rfl:purger-documents', function (PurgerDocuments $purger) {
    $resultat = $purger();

    if (! $resultat) {
        $this->info('Aucun événement à purger.');

        return;
    }

    foreach ($resultat as $id => $nombre) {
        $this->info(Evenement::find($id)->nom." : {$nombre} document(s) purgé(s).");
    }
})->purpose('Supprime les documents (consentements, décharges, santé) des événements passés depuis 12 mois');

Schedule::command('rfl:purger-documents')->dailyAt('03:00')->withoutOverlapping();

// Relances automatiques du spec : documents manquants à J-15, paiement non abouti après 24 h.
Artisan::command('rfl:relancer-documents', function (Relances $relances) {
    $this->info($relances->documents().' relance(s) de documents envoyée(s).');
})->purpose('Relance, 15 jours avant l\'événement, les inscriptions dont la décharge ou le questionnaire manque');

Artisan::command('rfl:relancer-paiements', function (Relances $relances) {
    $this->info($relances->paiements().' relance(s) de paiement envoyée(s).');
})->purpose('Relance les commandes restées en attente 24 h après un paiement non abouti');

Schedule::command('rfl:relancer-documents')->dailyAt('10:00')->withoutOverlapping();
Schedule::command('rfl:relancer-paiements')->hourly()->withoutOverlapping();
