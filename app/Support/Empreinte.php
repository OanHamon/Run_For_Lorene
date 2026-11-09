<?php

namespace App\Support;

use GdImage;
use RuntimeException;

/**
 * TRACK côté serveur : l'empreinte d'un billet dessinée en image fixe (PNG), pour le billet PDF.
 *
 * Reprise fidèle, en PHP, du code du navigateur (resources/js/track/) :
 * - code.js et rs.js : le jeton devient une couleur par cellule des semelles (Reed–Solomon puis
 *   brouillage) ;
 * - masque.js : la paire de semelles pixellisée, le cœur, les particules (même hasard, même graine).
 *
 * Mêmes entrées, mêmes cellules et mêmes particules que l'empreinte affichée à l'écran : le lecteur
 * du poste de retrait (lecteur.js) lit donc le billet imprimé comme l'écran. Le banc
 * tests/js/track-billet.mjs compare les deux versions et fait lire l'image produite ici.
 */
class Empreinte
{
    /** Pixels de masque par unité du SVG (FINESSE de masque.js). */
    private const FINESSE = 3;

    private const DONNEES = 15;

    private const RESERVE_COEUR = ['demiLargeur' => 1.14, 'haut' => 1.25, 'bas' => 1, 'marge' => 7, 'max' => 0.15];

    /** Couleurs par nuance (empreinte.js) : contour, secondaire, cœur, cellule foncée, cellule claire. */
    private const NUANCES = ['vert', 'vert-doux', 'moutarde', 'vert-sombre', 'vert-doux'];

    /** Écart entre particules pour l'impression (celui du banc de lecture). */
    public const ESPACEMENT = 3.8;

    private static ?array $gabarit = null;

    private static ?array $masque = null;

    private static ?array $exp = null;

    private static ?array $log = null;

    // ---------------------------------------------------------------- code.js

    public static function gabarit(): array
    {
        return self::$gabarit ??= json_decode((string) file_get_contents(resource_path('js/track/gabarit.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /** Couleur de chaque cellule du gabarit (1 foncée, 0 claire), ou null si le jeton n'est pas codable. */
    public static function cellules(string $jeton): ?array
    {
        if (! preg_match('/^RFL-(\d{1,10})-([a-f0-9]{20})$/', trim($jeton), $m) || (int) $m[1] > 0xFFFFFFFF) {
            return null;
        }

        $g = self::gabarit();
        $id = (int) $m[1];
        $donnees = [$g['version'], ($id >> 24) & 255, ($id >> 16) & 255, ($id >> 8) & 255, $id & 255,
            ...array_map('hexdec', str_split($m[2], 2))];

        $n = count($g['cellules']);
        $octets = intdiv($n, 8);
        $bloc = self::encoder($donnees, $octets - self::DONNEES);

        // Suite de brouillage fixe (xorshift 32 bits, graine « RFL3 »), comme code.js.
        $a = 0x52464C33;
        $cellules = [];
        for ($i = 0; $i < $n; $i++) {
            $a = ($a ^ ($a << 13)) & 0xFFFFFFFF;
            $a ^= $a >> 17;
            $a = ($a ^ ($a << 5)) & 0xFFFFFFFF;
            $bit = $i < $octets * 8 ? ($bloc[$i >> 3] >> (7 - ($i & 7))) & 1 : $i & 1;
            $cellules[] = $bit ^ ($a & 1);
        }

        return $cellules;
    }

    /** Reed–Solomon sur GF(256), polynôme 0x11d : `n` octets de correction ajoutés au message (rs.js). */
    private static function encoder(array $message, int $n): array
    {
        if (self::$exp === null) {
            $exp = array_fill(0, 512, 0);
            $log = array_fill(0, 256, 0);
            $x = 1;
            for ($i = 0; $i < 255; $i++) {
                $exp[$i] = $x;
                $log[$x] = $i;
                $x <<= 1;
                if ($x & 0x100) {
                    $x ^= 0x11D;
                }
            }
            for ($i = 255; $i < 512; $i++) {
                $exp[$i] = $exp[$i - 255];
            }
            [self::$exp, self::$log] = [$exp, $log];
        }
        $mul = fn (int $a, int $b) => $a && $b ? self::$exp[self::$log[$a] + self::$log[$b]] : 0;

        $gen = [1];
        for ($i = 0; $i < $n; $i++) {
            $facteur = [1, self::$exp[$i % 255]];
            $r = array_fill(0, count($gen) + 1, 0);
            foreach ($facteur as $j => $q) {
                foreach ($gen as $k => $p) {
                    $r[$k + $j] ^= $mul($p, $q);
                }
            }
            $gen = $r;
        }

        $out = [...$message, ...array_fill(0, $n, 0)];
        for ($i = 0; $i < count($message); $i++) {
            $c = $out[$i];
            if ($c) {
                for ($j = 1; $j < count($gen); $j++) {
                    $out[$i + $j] ^= $mul($gen[$j], $c);
                }
            }
        }

        return [...$message, ...array_slice($out, count($message))];
    }

    // ---------------------------------------------------------------- masque.js

    /** Masque des semelles (calculé une fois) : plein (chaîne d'octets), largeur, hauteur, boite, coeur. */
    public static function masque(): array
    {
        return self::$masque ??= self::pixelliser((string) file_get_contents(resource_path('svg/track/semelles.svg')));
    }

    private static function pixelliser(string $source): array
    {
        preg_match('/\sd="([^"]+)"/', $source, $d);
        preg_match('/viewBox="([^"]+)"/', $source, $v);
        $vue = array_map('floatval', preg_split('/\s+/', trim($v[1])));

        $polygones = [];
        foreach (preg_split('/(?=M)/', $d[1]) as $p) {
            if (trim($p) === '') {
                continue;
            }
            preg_match_all('/-?\d+\.?\d*/', $p, $nombres);
            $n = array_map('floatval', $nombres[0]);
            $pts = [[$n[0], $n[1]]];
            $cur = $pts[0];
            for ($i = 2; $i + 6 <= count($n); $i += 6) {
                [$x1, $y1, $x2, $y2, $x3, $y3] = array_slice($n, $i, 6);
                for ($k = 1; $k <= 16; $k++) {
                    $t = $k / 16;
                    $a = 1 - $t;
                    $pts[] = [$a * $a * $a * $cur[0] + 3 * $a * $a * $t * $x1 + 3 * $a * $t * $t * $x2 + $t * $t * $t * $x3,
                        $a * $a * $a * $cur[1] + 3 * $a * $a * $t * $y1 + 3 * $a * $t * $t * $y2 + $t * $t * $t * $y3];
                }
                $cur = [$x3, $y3];
            }
            $polygones[] = $pts;
        }

        $largeur = (int) ceil($vue[2] * self::FINESSE);
        $hauteur = (int) ceil($vue[3] * self::FINESSE);
        $plein = str_repeat("\0", $largeur * $hauteur);
        for ($py = 0; $py < $hauteur; $py++) {
            $y = ($py + 0.5) / self::FINESSE;
            $xs = [];
            foreach ($polygones as $P) {
                $nb = count($P);
                for ($j = 0; $j < $nb; $j++) {
                    [$xa, $ya] = $P[$j];
                    [$xb, $yb] = $P[($j + 1) % $nb];
                    if (($ya <= $y) !== ($yb <= $y)) {
                        $xs[] = $xa + (($y - $ya) * ($xb - $xa)) / ($yb - $ya);
                    }
                }
            }
            sort($xs);
            for ($k = 0; $k + 1 < count($xs); $k += 2) {
                $fin = min($largeur, self::arrondi($xs[$k + 1] * self::FINESSE));
                for ($px = max(0, self::arrondi($xs[$k] * self::FINESSE)); $px < $fin; $px++) {
                    $plein[$py * $largeur + $px] = "\1";
                }
            }
        }

        // Boîte englobante de la forme.
        $x0 = $largeur;
        $y0 = $hauteur;
        $x1 = 0;
        $y1 = 0;
        for ($y = 0; $y < $hauteur; $y++) {
            $ligne = substr($plein, $y * $largeur, $largeur);
            $g = strpos($ligne, "\1");
            if ($g === false) {
                continue;
            }
            $x0 = min($x0, $g);
            $x1 = max($x1, strrpos($ligne, "\1"));
            $y0 = min($y0, $y);
            $y1 = max($y1, $y);
        }

        $m = [
            'plein' => $plein, 'largeur' => $largeur, 'hauteur' => $hauteur,
            'boite' => ['x' => $x0 / self::FINESSE, 'y' => $y0 / self::FINESSE, 'l' => ($x1 - $x0) / self::FINESSE, 'h' => ($y1 - $y0) / self::FINESSE],
        ];
        $m['coeur'] = self::placerCoeur($m);

        return $m;
    }

    /** Plus grand cœur qui tient entre les deux semelles, le plus près possible du milieu (placerCoeur). */
    private static function placerCoeur(array $m): ?array
    {
        $C = self::RESERVE_COEUR;
        $boite = $m['boite'];
        $cxp = self::arrondi(($boite['x'] + $boite['l'] / 2) * self::FINESSE);

        $libre = [];
        for ($py = 0; $py < $m['hauteur']; $py++) {
            $g = 0;
            $d = 0;
            while ($cxp - $g >= 0 && $m['plein'][$py * $m['largeur'] + $cxp - $g] === "\0") {
                $g++;
            }
            while ($cxp + $d < $m['largeur'] && $m['plein'][$py * $m['largeur'] + $cxp + $d] === "\0") {
                $d++;
            }
            $libre[$py] = min($g, $d) / self::FINESSE;
        }
        $libreEntre = function (float $ya, float $yb) use ($libre): float {
            $min = INF;
            for ($py = (int) floor($ya * self::FINESSE); $py <= (int) ceil($yb * self::FINESSE); $py++) {
                $min = min($min, $libre[$py] ?? 0);
            }

            return $min;
        };

        $yMin = $boite['y'] + $boite['h'] * 0.12;
        $yMax = $boite['y'] + $boite['h'] * 0.88;
        $milieu = $boite['y'] + $boite['h'] / 2;
        for ($largeur = self::arrondi($boite['l'] * $C['max']); $largeur >= 14; $largeur--) {
            $unite = $largeur / ($C['demiLargeur'] * 2);
            $hauteur = $unite * ($C['haut'] + $C['bas']);
            $meilleur = null;
            for ($haut = $yMin; $haut + $hauteur <= $yMax; $haut += 1) {
                if ($libreEntre($haut - $C['marge'], $haut + $hauteur + $C['marge']) < $largeur / 2 + $C['marge']) {
                    continue;
                }
                $cy = $haut + $C['haut'] * $unite;
                if (! $meilleur || abs($haut + $hauteur / 2 - $milieu) < abs($meilleur['y'] + $hauteur / 2 - $milieu)) {
                    $meilleur = ['x' => $cxp / self::FINESSE, 'y' => $cy, 'unite' => $unite];
                }
            }
            if ($meilleur) {
                return ['cx' => $meilleur['x'], 'cy' => $meilleur['y'], 'unite' => $meilleur['unite']];
            }
        }

        return null;
    }

    private static function dansCoeur(array $c, float $x, float $y): bool
    {
        $u = ($x - $c['cx']) / $c['unite'];
        $v = -($y - $c['cy']) / $c['unite'];
        $a = $u * $u + $v * $v - 1;

        return $a * $a * $a - $u * $u * $v * $v * $v <= 0;
    }

    private static function disqueDansCoeur(array $c, float $x, float $y, float $r): bool
    {
        if (! self::dansCoeur($c, $x, $y)) {
            return false;
        }
        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2;
            if (! self::dansCoeur($c, $x + cos($a) * $r, $y + sin($a) * $r)) {
                return false;
            }
        }

        return true;
    }

    private static function dedans(array $m, float $x, float $y): bool
    {
        $px = self::arrondi($x * self::FINESSE);
        $py = self::arrondi($y * self::FINESSE);

        return $px >= 0 && $py >= 0 && $px < $m['largeur'] && $py < $m['hauteur'] && $m['plein'][$py * $m['largeur'] + $px] === "\1";
    }

    private static function disqueDedans(array $m, float $x, float $y, float $r): bool
    {
        if (! self::dedans($m, $x, $y)) {
            return false;
        }
        for ($i = 0; $i < 8; $i++) {
            $a = ($i / 8) * M_PI * 2;
            if (! self::dedans($m, $x + cos($a) * $r, $y + sin($a) * $r)) {
                return false;
            }
        }

        return true;
    }

    /** Générateur pseudo-aléatoire de masque.js (FNV-1a puis mulberry32), sur 32 bits comme en JS. */
    private static function hasard(string $graine): \Closure
    {
        $h = 2166136261;
        foreach (mb_str_split($graine) as $c) {
            $h = self::imul($h ^ mb_ord($c), 16777619);
        }
        $a = $h;

        return function () use (&$a): float {
            $a = ($a + 0x6D2B79F5) & 0xFFFFFFFF;
            $t = $a;
            $t = self::imul($t ^ ($t >> 15), $t | 1);
            $t = $t ^ (($t + self::imul($t ^ ($t >> 7), $t | 61)) & 0xFFFFFFFF);

            return (($t ^ ($t >> 14)) & 0xFFFFFFFF) / 4294967296;
        };
    }

    /** Math.imul sur des entiers non signés 32 bits (résultat non signé). */
    private static function imul(int $a, int $b): int
    {
        $a &= 0xFFFFFFFF;
        $b &= 0xFFFFFFFF;

        return (($a * ($b & 0xFFFF)) + ((($a * ($b >> 16)) & 0xFFFF) << 16)) & 0xFFFFFFFF;
    }

    /** Math.round de JS (demi vers +∞). */
    private static function arrondi(float $x): int
    {
        return (int) floor($x + 0.5);
    }

    /**
     * Particules de l'empreinte, en unités du SVG : liste de [x, y, r, nuance]
     * (0 contour, 1 secondaire, 2 cœur, 3 cellule foncée, 4 cellule claire si un jeton est donné).
     */
    public static function particules(string $graine, float $espacement = self::ESPACEMENT, ?array $cellules = null): array
    {
        $m = self::masque();
        $alea = self::hasard($graine);
        $boite = $m['boite'];
        $coeur = $m['coeur'];

        $fx = 0.03 + $alea() * 0.03;
        $fy = 0.025 + $alea() * 0.03;
        $px = $alea() * 10;
        $py = $alea() * 10;

        $liste = [];
        $pasY = $espacement * 0.87;
        $rangee = 0;
        for ($y = $boite['y']; $y <= $boite['y'] + $boite['h']; $y += $pasY, $rangee++) {
            $decalage = $rangee % 2 ? $espacement / 2 : 0;
            for ($x = $boite['x'] + $decalage; $x <= $boite['x'] + $boite['l']; $x += $espacement) {
                $jx = $x + ($alea() - 0.5) * $espacement * 0.55;
                $jy = $y + ($alea() - 0.5) * $espacement * 0.55;
                $r = $espacement * (0.28 + $alea() * 0.18);

                if ($coeur && self::disqueDansCoeur($coeur, $jx, $jy, $r * 0.6)) {
                    $alea(); // phase (animation seulement)
                    $liste[] = [$jx, $jy, $r, 2];

                    continue;
                }
                if (! self::disqueDedans($m, $jx, $jy, $r * 0.6)) {
                    continue;
                }
                $alea(); // phase
                $tache = sin($jx * $fx + $px) * sin($jy * $fy + $py);
                $liste[] = [$jx, $jy, $r, $tache > 0.62 || $alea() < 0.03 ? 1 : 0];
            }
        }

        if ($cellules !== null) {
            $g = self::gabarit();
            $c = $g['cellule'];
            [$x0, $y0] = $g['cellules'][0];
            $index = [];
            foreach ($g['cellules'] as $i => [$cx, $cy]) {
                $index[self::arrondi(($cx - $x0) / $c).','.self::arrondi(($cy - $y0) / $c)] = $i;
            }
            foreach ($liste as &$q) {
                if ($q[3] === 2) {
                    continue;
                }
                $i = $index[(int) floor(($q[0] - $x0) / $c).','.(int) floor(($q[1] - $y0) / $c)] ?? null;
                $q[3] = $i === null ? 0 : ($cellules[$i] ? 3 : 4);
            }
            unset($q);
        }

        return $liste;
    }

    // ---------------------------------------------------------------- image

    /**
     * Empreinte codée du jeton en PNG, fond blanc, `$resolution` pixels par unité du SVG
     * (5 : environ 1 600 px de large, net à l'impression sur 8 à 10 cm).
     */
    public static function png(string $jeton, int $resolution = 5): string
    {
        $cellules = self::cellules($jeton) ?? throw new RuntimeException('Jeton de billet impossible à coder.');
        $image = self::dessiner(self::particules($jeton, self::ESPACEMENT, $cellules), $resolution);

        ob_start();
        imagepng($image, null, 9);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    /** L'empreinte en data URI, à insérer dans le billet PDF. */
    public static function dataUri(string $jeton, int $resolution = 5): string
    {
        return 'data:image/png;base64,'.base64_encode(self::png($jeton, $resolution));
    }

    private static function dessiner(array $liste, int $res): GdImage
    {
        $boite = self::masque()['boite'];
        $marge = 6; // unités du SVG, comme la marge de l'écran
        $l = (int) ceil(($boite['l'] + 2 * $marge) * $res);
        $h = (int) ceil(($boite['h'] + 2 * $marge) * $res);
        $ox = ($marge - $boite['x']) * $res;
        $oy = ($marge - $boite['y']) * $res;

        // Dessin en double résolution puis réduction : bords des particules adoucis.
        $s = 2;
        $grande = imagecreatetruecolor($l * $s, $h * $s);
        imagefill($grande, 0, 0, imagecolorallocate($grande, 255, 255, 255));
        $couleurs = array_map(fn (string $nom) => imagecolorallocate($grande, ...sscanf(Charte::couleur($nom), '#%02x%02x%02x')), self::NUANCES);
        foreach ($liste as [$x, $y, $r, $nuance]) {
            $d = (int) round(2 * $r * $res * $s);
            imagefilledellipse($grande, (int) round(($ox + $x * $res) * $s), (int) round(($oy + $y * $res) * $s), $d, $d, $couleurs[$nuance]);
        }

        $image = imagecreatetruecolor($l, $h);
        imagecopyresampled($image, $grande, 0, 0, 0, 0, $l, $h, $l * $s, $h * $s);
        imagedestroy($grande);

        return $image;
    }
}
