<?php

namespace App\Support;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports tableur : Excel (.xlsx, une colonne par champ quel que soit le réglage régional de
 * Windows) ou CSV (spec : séparateur point-virgule, UTF-8 avec BOM). Les montants arrivent
 * en nombres : vrais nombres dans Excel, « 25,00 » dans le CSV. Les textes ne deviennent
 * jamais des formules.
 */
class Csv
{
    public const FORMATS = ['xlsx', 'csv'];

    /**
     * @param  string  $fichier  nom sans extension
     * @param  list<string>  $entetes
     * @param  iterable<array<int, mixed>>  $lignes
     */
    public static function telecharger(string $fichier, array $entetes, iterable $lignes, string $format = 'xlsx'): StreamedResponse|BinaryFileResponse
    {
        return $format === 'csv'
            ? self::csv("{$fichier}.csv", $entetes, $lignes)
            : self::xlsx("{$fichier}.xlsx", $entetes, $lignes);
    }

    /** Format demandé (?format=csv), Excel par défaut. */
    public static function format(?string $demande): string
    {
        return in_array($demande, self::FORMATS, true) ? $demande : 'xlsx';
    }

    private static function csv(string $fichier, array $entetes, iterable $lignes): StreamedResponse
    {
        return response()->streamDownload(function () use ($entetes, $lignes) {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\xEF\xBB\xBF");
            fputcsv($sortie, $entetes, ';', '"', '');

            foreach ($lignes as $ligne) {
                fputcsv($sortie, array_map(self::celluleCsv(...), $ligne), ';', '"', '');
            }

            fclose($sortie);
        }, $fichier, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function xlsx(string $fichier, array $entetes, iterable $lignes): BinaryFileResponse
    {
        $chemin = tempnam(sys_get_temp_dir(), 'rfl-export-');
        $writer = new Writer;
        $writer->openToFile($chemin);

        $writer->addRow(new Row(array_map(fn ($e) => new Cell\StringCell((string) $e, null), $entetes), (new Style)->setFontBold()));

        foreach ($lignes as $ligne) {
            $writer->addRow(new Row(array_map(self::celluleXlsx(...), $ligne)));
        }

        $writer->close();

        return response()->download($chemin, $fichier, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    private static function celluleXlsx(mixed $valeur): Cell
    {
        return match (true) {
            $valeur === null || $valeur === '' => new Cell\EmptyCell(null, null),
            is_int($valeur), is_float($valeur) => new Cell\NumericCell($valeur, null),
            // Texte pur : « =Durand » reste un nom, jamais une formule.
            default => new Cell\StringCell((string) $valeur, null),
        };
    }

    /** Montants « 25,00 » ; neutralise les formules (=, +, -, @) qu'un tableur exécuterait à l'ouverture. */
    private static function celluleCsv(mixed $valeur): string
    {
        if (is_float($valeur)) {
            return number_format($valeur, 2, ',', '');
        }

        $valeur = (string) $valeur;

        return preg_match('/^[=+\-@\t\r]/', $valeur) && ! is_numeric($valeur) ? "'".$valeur : $valeur;
    }
}
