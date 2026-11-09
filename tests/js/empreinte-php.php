<?php

/**
 * Aide du banc tests/js/track-billet.mjs : pour chaque jeton passé en argument, l'empreinte
 * calculée par App\Support\Empreinte (cellules, particules) et son image PNG en niveaux de gris
 * (fichier brut, un octet par pixel) dans le dossier donné par --dossier.
 *
 *   php tests/js/empreinte-php.php --dossier /tmp/x RFL-12-0123456789abcdef0123
 */

use App\Support\Empreinte;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$args = array_slice($argv, 1);
$dossier = null;
if (($k = array_search('--dossier', $args, true)) !== false) {
    $dossier = $args[$k + 1];
    array_splice($args, $k, 2);
}

$sortie = [];
foreach ($args as $jeton) {
    $debut = microtime(true);
    $cellules = Empreinte::cellules($jeton);
    $particules = Empreinte::particules($jeton, Empreinte::ESPACEMENT, $cellules);
    $png = Empreinte::png($jeton);
    $duree = microtime(true) - $debut;

    $image = null;
    if ($dossier) {
        $gd = imagecreatefromstring($png);
        [$l, $h] = [imagesx($gd), imagesy($gd)];
        $gris = '';
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $l; $x++) {
                $c = imagecolorat($gd, $x, $y);
                $gris .= chr((int) round(0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255)));
            }
        }
        $fichier = $dossier.'/'.$jeton.'.gray';
        file_put_contents($fichier, $gris);
        $image = ['fichier' => $fichier, 'l' => $l, 'h' => $h];
    }

    $sortie[$jeton] = ['cellules' => $cellules, 'particules' => $particules, 'image' => $image, 'ms' => round($duree * 1000), 'octetsPng' => strlen($png)];
}

echo json_encode($sortie);
