<?php

namespace App\Support;

use App\Models\Inscription;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Common\Version;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Exception\WriterException;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
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

    /**
     * Grille brute du QR d'un texte, pour l'empreinte TRACK (le QR dessiné en semelles).
     * Correction d'erreur maximale (H, 30 %) : les particules décoratives autour de chaque module
     * brouillent un peu l'image. Version 4 (33 × 33) imposée tant que le texte y tient, pour que
     * toutes les empreintes aient la même trame ; sans préfixe ECI, comme un QR ordinaire.
     *
     * @return array{taille: int, version: int, modules: string} modules : '1' foncé, '0' clair, ligne après ligne
     */
    public static function matrice(string $contenu): array
    {
        try {
            $qr = Encoder::encode($contenu, ErrorCorrectionLevel::H(), 'ISO-8859-1', Version::getVersionForNumber(4), false);
        } catch (WriterException) {
            $qr = Encoder::encode($contenu, ErrorCorrectionLevel::H(), 'ISO-8859-1', null, false);
        }

        $grille = $qr->getMatrix();
        $modules = '';
        for ($y = 0; $y < $grille->getHeight(); $y++) {
            for ($x = 0; $x < $grille->getWidth(); $x++) {
                $modules .= $grille->get($x, $y) === 1 ? '1' : '0';
            }
        }

        return ['taille' => $grille->getWidth(), 'version' => $qr->getVersion()->getVersionNumber(), 'modules' => $modules];
    }

    /** Lien vers le billet en plein écran, sans connexion (signé, valable tant que la clé ne change pas). */
    public static function url(Inscription $inscription): string
    {
        return URL::signedRoute('billet', ['inscription' => $inscription->id_inscription]);
    }

    private static function signature(int $id): string
    {
        return substr(hash_hmac('sha256', "billet|{$id}", (string) config('app.key')), 0, 20);
    }
}
