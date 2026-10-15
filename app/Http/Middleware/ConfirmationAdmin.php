<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrée dans le back-office : l'admin confirme par un code reçu par email, puis la
 * confirmation vaut quelques heures (config rfl.admin_confirmation_heures). Une session
 * neuve, y compris rouverte par le cookie « se souvenir de moi », n'est jamais confirmée.
 * La connexion par code ne dispense pas de cette étape.
 */
class ConfirmationAdmin
{
    public const CLE = 'admin.confirme_le';

    public const CLE_URL = 'admin.url_demandee';

    public function handle(Request $request, Closure $next): Response
    {
        if (self::estConfirmee($request)) {
            return $next($request);
        }

        abort_if($request->expectsJson(), 403, 'Confirmation requise.');

        // Seule une page consultée (GET) peut être rouverte après confirmation.
        if ($request->isMethod('GET')) {
            $request->session()->put(self::CLE_URL, $request->fullUrl());
        }

        return redirect()->route('admin.confirmation');
    }

    public static function estConfirmee(Request $request): bool
    {
        $confirmeLe = (int) $request->session()->get(self::CLE, 0);

        return $confirmeLe > 0
            && $confirmeLe + config('rfl.admin_confirmation_heures') * 3600 > now()->getTimestamp();
    }
}
