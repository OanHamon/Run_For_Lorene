<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Durée d'une session admin : la connexion (mot de passe puis code reçu par email, voir
 * Admin\ConnexionController) vaut quelques heures (config rfl.admin_confirmation_heures).
 * Passé ce délai, ou sans code validé dans cette session, l'admin est déconnecté et repasse
 * par la connexion complète.
 */
class ConfirmationAdmin
{
    public const CLE = 'admin.confirme_le';

    public function handle(Request $request, Closure $next): Response
    {
        if (self::estConfirmee($request)) {
            return $next($request);
        }

        Auth::guard('admin')->logout();
        $request->session()->forget(self::CLE);

        abort_if($request->expectsJson(), 401, 'Connexion requise.');

        // Seule une page consultée (GET) peut être rouverte après la connexion.
        return $request->isMethod('GET')
            ? redirect()->guest(route('admin.connexion'))->with('statut', 'Ta session a expiré : reconnecte-toi.')
            : redirect()->route('admin.connexion')->with('statut', 'Ta session a expiré : reconnecte-toi.');
    }

    public static function estConfirmee(Request $request): bool
    {
        $confirmeLe = (int) $request->session()->get(self::CLE, 0);

        return $confirmeLe > 0
            && $confirmeLe + config('rfl.admin_confirmation_heures') * 3600 > now()->getTimestamp();
    }
}
