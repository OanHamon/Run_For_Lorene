<?php

namespace App\Support;

/** Formats d'affichage communs (back-office, exports). */
class Format
{
    /** 25 → « 25 € », 12.5 → « 12,50 € ». */
    public static function euros(mixed $montant): string
    {
        $montant = (float) $montant;

        return number_format($montant, fmod($montant, 1.0) == 0.0 ? 0 : 2, ',', ' ').' €';
    }

    /** Montant pour un tableur français : « 12,50 ». */
    public static function decimal(mixed $montant): string
    {
        return number_format((float) $montant, 2, ',', '');
    }
}
