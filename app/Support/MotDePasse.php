<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Règle unique des mots de passe choisis sur le site (création de compte, activation,
 * « Mon compte ») : 6 caractères au moins, une majuscule, une minuscule et un chiffre.
 * En production, refus en plus des mots de passe déjà sortis dans une fuite connue
 * (service Have I Been Pwned, seul un préfixe du hash est envoyé).
 */
class MotDePasse
{
    public const LONGUEUR_MIN = 6;

    /** Texte d'aide affiché sous chaque champ « nouveau mot de passe ». */
    public const AIDE = '6 caractères au moins, avec une majuscule, une minuscule et un chiffre.';

    public static function regle(bool $production): Password
    {
        $regle = Password::min(self::LONGUEUR_MIN)->mixedCase()->numbers();

        return $production ? $regle->uncompromised() : $regle;
    }
}
