<?php

/**
 * Tâche planifiée de l'hébergement OVH (Web Cloud → Hébergement → onglet « Tâches planifiées -
 * Cron ») : à lancer toutes les heures, avec la même version de PHP que le site.
 *
 * Filet de sécurité du réveil externe (adresse /reveil/{clé}, chaque minute) : même travail,
 * tâches planifiées arrivées à échéance puis file des emails. Voir App\Services\Reveil.
 * Hors du dossier public : il ne s'ouvre pas depuis le web.
 */

use App\Services\Reveil;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$resultat = $app->make(Reveil::class)();

echo date('c').' réveil : '.json_encode($resultat, JSON_UNESCAPED_UNICODE).PHP_EOL;
