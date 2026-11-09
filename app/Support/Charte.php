<?php

namespace App\Support;

/**
 * Couleurs de la charte pour ce qui ne passe pas par Tailwind (PDF, images générées côté serveur).
 * Mêmes noms et mêmes valeurs que les tokens `@theme` de resources/css/app.css : un test vérifie
 * qu'ils restent identiques.
 */
final class Charte
{
    public const COULEURS = [
        'vert' => '#006B61',
        'vert-doux' => '#5FBAAE',
        'moutarde' => '#D9B21E',
        'vert-sombre' => '#23463F',
        'vert-clair' => '#BEECE6',
        'gris-texte' => '#4F6A64',
        'fond' => '#F1FAF8',
        'bordure' => '#D2E8E3',
    ];

    public static function couleur(string $nom): string
    {
        return self::COULEURS[$nom];
    }
}
