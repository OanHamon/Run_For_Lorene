<?php

use App\Models\Evenement;
use App\Services\Admin\EnvoisProgrammes;
use App\Services\Inscriptions\PurgerDocuments;
use App\Services\Inscriptions\Relances;
use App\Services\Paiement\AbandonnerCommandesOubliees;
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

// Relance automatique des documents manquants (N jours avant l'événement, réglage admin, en pause par défaut).
Artisan::command('rfl:relancer-documents', function (Relances $relances) {
    $this->info($relances->documents().' relance(s) de documents envoyée(s).');
})->purpose('Relance, N jours avant l\'événement (réglage admin), les inscriptions dont la décharge ou le questionnaire manque');

// Documents : toutes les heures, l'heure d'envoi se règle dans l'admin (10 h par défaut).
Schedule::command('rfl:relancer-documents')->hourly()->withoutOverlapping();

// Inscription d'une traite : une commande non payée à la fin de sa session de paiement redevient
// un brouillon (filet de sécurité si le webhook checkout.session.expired n'est pas arrivé).
Artisan::command('rfl:abandonner-commandes', function (AbandonnerCommandesOubliees $abandonner) {
    $this->info($abandonner().' commande(s) non payée(s) remise(s) en brouillon.');
})->purpose('Remet en brouillon les commandes restées sans paiement après la fin de leur session');

Schedule::command('rfl:abandonner-commandes')->everyFiveMinutes()->withoutOverlapping();

// Relances programmées dans l'admin (date précise, N jours avant l'événement, répétées).
Artisan::command('rfl:envois-programmes', function (EnvoisProgrammes $envois) {
    $this->info($envois().' envoi(s) programmé(s) parti(s).');
})->purpose('Envoie les relances programmées dans l\'admin arrivées à leur date');

Schedule::command('rfl:envois-programmes')->everyFiveMinutes()->withoutOverlapping();
