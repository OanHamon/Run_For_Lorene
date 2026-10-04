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
 */
class CodeEmail
{
    public const VALIDITE_MINUTES = 15;

    public const ESSAIS_MAX = 5;

    public const DELAI_RENVOI_SECONDES = 60;

    public const ENVOIS_MAX_PAR_HEURE = 6;

    public function envoyer(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $expireA = now()->addMinutes(self::VALIDITE_MINUTES);

        Cache::put($this->cle($user), [
            'hash' => Hash::make($code),
            'essais' => 0,
            'expire_a' => $expireA->getTimestamp(),
        ], $expireA);

        RateLimiter::hit($this->cleRenvoi($user), self::DELAI_RENVOI_SECONDES);
        RateLimiter::hit($this->cleQuota($user), 3600);

        if (config('auth.otp_dans_les_logs')) {
            Log::info("[Code email] {$user->email} : {$code}");
        }

        $user->notify(new CodeEmailNotification($code));
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

        if (Hash::check(preg_replace('/\D/', '', $code), $entree['hash'])) {
            Cache::forget($cle);

            return 'ok';
        }

        $entree['essais']++;

        if ($entree['essais'] >= self::ESSAIS_MAX) {
            Cache::forget($cle);

            return 'expire';
        }

        Cache::put($cle, $entree, now()->setTimestamp($entree['expire_a']));

        return 'invalide';
    }

    private function cle(User $user): string
    {
        return "code-email:{$user->id_user}";
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
