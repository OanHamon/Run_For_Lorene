<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Notifications\CodeEmailNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Code à 6 chiffres envoyé par email. Il sert à la fois à vérifier l'adresse d'un nouveau
 * compte et à se connecter sans mot de passe (équivalent du « lien magique » du spec) :
 * dans les deux cas, il prouve que la personne a accès à la boîte mail.
 *
 * Le code est stocké haché en cache, valable 15 minutes, invalidé après 5 essais ratés.
 * Les envois sont limités (un par minute, six par heure) : sans cette limite, redemander un
 * code après 5 essais ratés permettrait de deviner le code par force brute.
 */
class CodeEmail
{
    public const VALIDITE_MINUTES = 15;

    public const ESSAIS_MAX = 5;

    public const DELAI_RENVOI_SECONDES = 60;

    public const ENVOIS_MAX_PAR_HEURE = 6;

    /** Faux (et rien n'est envoyé) si le délai entre deux envois ou le quota horaire n'est pas respecté. */
    public function envoyer(User $user): bool
    {
        if ($this->secondesAvantRenvoi($user) > 0) {
            return false;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $expireA = now()->addMinutes(self::VALIDITE_MINUTES);

        Cache::put($this->cle($user), [
            'hash' => Hash::make($code),
            'expire_a' => $expireA->getTimestamp(),
        ], $expireA);
        Cache::forget($this->cleEssais($user));

        RateLimiter::hit($this->cleRenvoi($user), self::DELAI_RENVOI_SECONDES);
        RateLimiter::hit($this->cleQuota($user), 3600);

        if (config('auth.otp_dans_les_logs') && ! app()->isProduction()) {
            Log::info("[Code email] {$user->email} : {$code}");
        }

        $user->notify(new CodeEmailNotification($code));

        return true;
    }

    /** Secondes à attendre avant de pouvoir redemander un code (0 = possible tout de suite). */
    public function secondesAvantRenvoi(User $user): int
    {
        if (RateLimiter::tooManyAttempts($this->cleQuota($user), self::ENVOIS_MAX_PAR_HEURE)) {
            return RateLimiter::availableIn($this->cleQuota($user));
        }

        return RateLimiter::tooManyAttempts($this->cleRenvoi($user), 1)
            ? RateLimiter::availableIn($this->cleRenvoi($user))
            : 0;
    }

    public function aUnCodeActif(User $user): bool
    {
        return Cache::has($this->cle($user));
    }

    /**
     * @return 'ok'|'invalide'|'expire'
     */
    public function verifier(User $user, string $code): string
    {
        $cle = $this->cle($user);
        $entree = Cache::get($cle);

        if (! $entree || $entree['expire_a'] <= now()->getTimestamp()) {
            return 'expire';
        }

        // Compteur incrémenté avant la vérification, de façon atomique : des requêtes lancées en
        // parallèle ne peuvent pas dépasser le nombre d'essais autorisés.
        $essais = Cache::increment($this->cleEssais($user));

        if ($essais > self::ESSAIS_MAX) {
            Cache::forget($cle);

            return 'expire';
        }

        if (Hash::check(preg_replace('/\D/', '', $code), $entree['hash'])) {
            Cache::forget($cle);
            Cache::forget($this->cleEssais($user));

            return 'ok';
        }

        if ($essais >= self::ESSAIS_MAX) {
            Cache::forget($cle);

            return 'expire';
        }

        return 'invalide';
    }

    private function cle(User $user): string
    {
        return "code-email:{$user->id_user}";
    }

    private function cleEssais(User $user): string
    {
        return "code-email-essais:{$user->id_user}";
    }

    private function cleRenvoi(User $user): string
    {
        return "code-email-renvoi:{$user->id_user}";
    }

    private function cleQuota(User $user): string
    {
        return "code-email-quota:{$user->id_user}";
    }
}
