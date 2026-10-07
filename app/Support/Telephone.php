<?php

namespace App\Support;

/** Téléphone au format français : stocké en 10 chiffres, affiché par paires. */
class Telephone
{
    public const REGLE = ['nullable', 'regex:/^0[1-9]\d{8}$/'];

    public const MESSAGE = 'Numéro français attendu, par exemple 06 12 34 56 78.';

    /** « +33 6 12-34.56.78 » → « 0612345678 ». */
    public static function normaliser(string $tel): string
    {
        $tel = preg_replace('/[\s.\-()]/', '', trim($tel));

        return preg_replace('/^(\+|00)33/', '0', $tel);
    }

    /** « 0612345678 » → « 06 12 34 56 78 ». */
    public static function lisible(?string $tel): string
    {
        return $tel ? trim(chunk_split($tel, 2, ' ')) : '';
    }
}
