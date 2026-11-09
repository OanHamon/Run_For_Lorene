<?php

namespace App\Support;

use App\Models\Inscription;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\URL;

/**
 * Billet QR d'une inscription validée. Le QR ne contient aucune donnée personnelle : un jeton
 * « RFL-{n° d'inscription}-{signature} », signé avec la clé de l'application, impossible à
 * fabriquer pour une autre inscription. Il est relu au retrait des dossards ; une inscription
 * annulée entre-temps est refusée à ce moment-là.
 */
class Billet
{
    public static function jeton(Inscription $inscription): string
    {
        return 'RFL-'.$inscription->id_inscription.'-'.self::signature($inscription->id_inscription);
    }

    /** L'inscription du jeton lu, ou null si le jeton est faux ou d'un autre format. */
    public static function lire(string $jeton): ?Inscription
    {
        if (! preg_match('/^RFL-(\d{1,10})-([a-f0-9]{20})$/', trim($jeton), $m)) {
            return null;
        }

        return hash_equals(self::signature((int) $m[1]), $m[2]) ? Inscription::find((int) $m[1]) : null;
    }

    /** QR du billet en SVG (à insérer tel quel dans la page), carré de $taille pixels. */
    public static function svg(Inscription $inscription, int $taille = 220): string
    {
        return self::qr(self::jeton($inscription), $taille);
    }

    /** QR code SVG d'un texte quelconque (lien d'un poste de retrait…). */
    public static function qr(string $contenu, int $taille = 220): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($taille, 1), new SvgImageBackEnd));

        // Retire la déclaration XML pour l'insérer dans une page HTML.
        return preg_replace('/^<\?xml[^>]*>\s*/', '', $writer->writeString($contenu));
    }

    /** Lien vers le billet en plein écran, sans connexion (signé, valable tant que la clé ne change pas). */
    public static function url(Inscription $inscription): string
    {
        return URL::signedRoute('billet', ['inscription' => $inscription->id_inscription]);
    }

    /** Lien vers le billet PDF à imprimer (signé, sans connexion, comme le billet plein écran). */
    public static function urlPdf(Inscription $inscription): string
    {
        return URL::signedRoute('billet.pdf', ['inscription' => $inscription->id_inscription]);
    }

    /**
     * Billet imprimable (PDF A4) d'une inscription validée : l'empreinte des semelles (le billet),
     * un QR code classique en secours et le numéro de dossard (retrouvé par recherche au retrait).
     */
    public static function pdf(Inscription $inscription): string
    {
        $inscription->loadMissing('participant.user', 'tarif.course', 'evenement');
        $jeton = self::jeton($inscription);

        return Pdf::loadView('billet-pdf', [
            'inscription' => $inscription,
            'empreinte' => Empreinte::dataUri($jeton, 4),
            'qr' => self::qrPng($jeton),
        ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true)->output();
    }

    public static function nomFichier(Inscription $inscription): string
    {
        return 'billet-run-for-lorene-'.($inscription->dossard ?? 'inscription-'.$inscription->id_inscription).'.pdf';
    }

    /** QR code en PNG (data URI) : modules nets à l'impression, sans dépendre du rendu SVG du PDF. */
    public static function qrPng(string $contenu, int $module = 12): string
    {
        $matrice = Encoder::encode($contenu, ErrorCorrectionLevel::M(), Encoder::DEFAULT_BYTE_MODE_ENCODING)->getMatrix();
        $n = $matrice->getWidth();
        $marge = 2;
        $cote = ($n + 2 * $marge) * $module;

        $image = imagecreatetruecolor($cote, $cote);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        // Vert sombre : contraste suffisant pour tous les lecteurs de QR code.
        $encre = imagecolorallocate($image, ...sscanf(Charte::couleur('vert-sombre'), '#%02x%02x%02x'));
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($matrice->get($x, $y) === 1) {
                    $x0 = ($x + $marge) * $module;
                    $y0 = ($y + $marge) * $module;
                    imagefilledrectangle($image, $x0, $y0, $x0 + $module - 1, $y0 + $module - 1, $encre);
                }
            }
        }

        ob_start();
        imagepng($image, null, 9);
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    private static function signature(int $id): string
    {
        return substr(hash_hmac('sha256', "billet|{$id}", (string) config('app.key')), 0, 20);
    }
}
