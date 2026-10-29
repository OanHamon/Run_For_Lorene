<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Storage;

/**
 * PDF d'une page « document provisoire » pour les textes légaux des seeders : sans fichier sur
 * le disque, le tunnel refuse de faire accepter un texte (TexteLegal::fichierPresent).
 * Les vrais textes sont publiés par l'admin (Événements → Textes légaux).
 */
class PdfProvisoire
{
    public static function ecrire(string $chemin, string $titre): void
    {
        if (Storage::disk('local')->exists($chemin)) {
            return;
        }

        Storage::disk('local')->put($chemin, self::contenu($titre));
    }

    private static function contenu(string $titre): string
    {
        $texte = fn (string $t) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], iconv('UTF-8', 'Windows-1252//TRANSLIT', $t));
        $flux = "BT /F1 18 Tf 72 760 Td ({$texte($titre)}) Tj ET\nBT /F1 12 Tf 72 730 Td ({$texte('Document provisoire de développement : le texte officiel sera publié par l\'association.')}) Tj ET";

        $objets = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Length '.strlen($flux)." >>\nstream\n{$flux}\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $positions = [];
        foreach ($objets as $i => $objet) {
            $positions[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$objet}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref
0 '.(count($objets) + 1)."\n0000000000 65535 f \n";
        foreach ($positions as $position) {
            $pdf .= sprintf("%010d 00000 n \n", $position);
        }

        return $pdf.'trailer << /Size '.(count($objets) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
