<?php

use App\Models\Evenement;
use App\Services\Inscriptions\PurgerDocuments;
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
