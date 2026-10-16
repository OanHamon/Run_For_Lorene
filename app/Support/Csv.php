<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV lisible colonne par colonne dans Excel, quel que soit le séparateur régional de
 * Windows (virgule ou point-virgule) :
 * - première ligne « sep=; » : Excel découpe alors sur le point-virgule ;
 * - encodage Windows-1252 : avec « sep=; », Excel ignore le BOM UTF-8 et casserait les
 *   accents ; Windows-1252 couvre le français (é, è, à, ç, œ…), le reste est translittéré ;
 * - fins de ligne Windows, montants « 25,00 », textes jamais interprétés comme formules.
 */
class Csv
{
    public const ENCODAGE = 'Windows-1252';

    /**
     * @param  string  $fichier  nom sans extension
     * @param  list<string>  $entetes
     * @param  iterable<array<int, mixed>>  $lignes
     */
    public static function telecharger(string $fichier, array $entetes, iterable $lignes): StreamedResponse
    {
        return response()->streamDownload(function () use ($entetes, $lignes) {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "sep=;\r\n");
            fputcsv($sortie, array_map(self::encoder(...), $entetes), ';', '"', '', "\r\n");

            foreach ($lignes as $ligne) {
                fputcsv($sortie, array_map(fn ($v) => self::encoder(self::cellule($v)), $ligne), ';', '"', '', "\r\n");
            }

            fclose($sortie);
        }, "{$fichier}.csv", ['Content-Type' => 'text/csv; charset='.self::ENCODAGE]);
    }

    /** Montants « 25,00 » ; neutralise les formules (=, +, -, @) qu'un tableur exécuterait à l'ouverture. */
    private static function cellule(mixed $valeur): string
    {
        if (is_float($valeur)) {
            return number_format($valeur, 2, ',', '');
        }

        $valeur = (string) $valeur;

        return preg_match('/^[=+\-@\t\r]/', $valeur) && ! is_numeric($valeur) ? "'".$valeur : $valeur;
    }

    /** UTF-8 → Windows-1252, en translittérant ce qui n'y existe pas (ł → l, emoji retiré). */
    private static function encoder(string $texte): string
    {
        $converti = @iconv('UTF-8', self::ENCODAGE.'//TRANSLIT//IGNORE', $texte);

        return $converti === false ? mb_convert_encoding($texte, self::ENCODAGE, 'UTF-8') : $converti;
    }
}
