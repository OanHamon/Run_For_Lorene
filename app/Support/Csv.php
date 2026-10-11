<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** Export CSV compatible tableur (spec) : séparateur point-virgule, UTF-8 avec BOM. */
class Csv
{
    /**
     * @param  list<string>  $entetes
     * @param  iterable<array<int, mixed>>  $lignes
     */
    public static function telecharger(string $fichier, array $entetes, iterable $lignes): StreamedResponse
    {
        return response()->streamDownload(function () use ($entetes, $lignes) {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\xEF\xBB\xBF");
            fputcsv($sortie, $entetes, ';', '"', '');

            foreach ($lignes as $ligne) {
                fputcsv($sortie, array_map(self::cellule(...), $ligne), ';', '"', '');
            }

            fclose($sortie);
        }, $fichier, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralise les formules (=, +, -, @) qu'un tableur exécuterait à l'ouverture. */
    private static function cellule(mixed $valeur): string
    {
        $valeur = (string) $valeur;

        return preg_match('/^[=+\-@\t\r]/', $valeur) && ! is_numeric($valeur) ? "'".$valeur : $valeur;
    }
}
