<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Lien « Créer mon mot de passe » d'un compte créé par l'association : signé, valable
 * 7 jours, utilisable tant que le compte n'a pas de mot de passe. La clé dépend de l'email et
 * du jeton de session du compte : corriger l'email ou réinitialiser l'accès rend caducs les
 * liens déjà envoyés.
 */
class Activation
{
    public const VALIDITE_JOURS = 7;

    /** @param  string|null  $suite  page de l'espace où arriver après (ex. l'inscription) */
    public static function url(User $user, ?string $suite = null): string
    {
        return URL::temporarySignedRoute('compte.activation', now()->addDays(self::VALIDITE_JOURS), array_filter([
            'user' => $user->id_user,
            'cle' => self::cle($user),
            'suite' => $suite,
        ]));
    }

    /**
     * Lien à mettre dans un email vers une page de l'espace : le lien d'activation tant que le
     * compte n'a pas de mot de passe (il y mène ensuite), sinon la page directement.
     *
     * @return array{url: string, active: bool}
     */
    public static function lienEspace(User $user, string $suite): array
    {
        return $user->mot_de_passe === null
            ? ['url' => self::url($user, $suite), 'active' => false]
            : ['url' => url($suite), 'active' => true];
    }

    public static function cle(User $user): string
    {
        return substr(hash_hmac('sha256', $user->id_user.'|'.$user->email.'|'.$user->remember_token, (string) config('app.key')), 0, 32);
    }

    /** Le lien est-il encore utilisable pour ce compte ? */
    public static function valable(User $user, ?string $cle): bool
    {
        return $user->mot_de_passe === null
            && $user->email !== null
            && is_string($cle)
            && hash_equals(self::cle($user), $cle);
    }

    /** Destination après activation : seulement une page de l'espace (pas de redirection ouverte). */
    public static function suite(?string $suite): string
    {
        return is_string($suite) && preg_match('#^/espace(/[\w/-]*)?$#', $suite) ? $suite : route('espace', absolute: false);
    }
}
