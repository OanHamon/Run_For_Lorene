<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Réserve une route aux admins d'un niveau d'accès minimal : `admin` (lecture), `admin:2`, `admin:3`. */
class NiveauAdmin
{
    public function handle(Request $request, Closure $next, int $niveau = Admin::LECTURE): Response
    {
        $user = $request->user();

        // Accès retiré pendant la session : on la ferme.
        if ($user && ! $user->peutAdmin()) {
            Auth::guard('admin')->logout();
            $request->session()->forget(ConfirmationAdmin::CLE);

            return redirect()->route('admin.connexion');
        }

        abort_unless($user?->peutAdmin($niveau), 403);

        return $next($request);
    }
}
