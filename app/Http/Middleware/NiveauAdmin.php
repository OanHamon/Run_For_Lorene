<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Réserve une route aux admins d'un niveau d'accès minimal : `admin` (lecture), `admin:2`, `admin:3`. */
class NiveauAdmin
{
    public function handle(Request $request, Closure $next, int $niveau = Admin::LECTURE): Response
    {
        abort_unless($request->user()?->peutAdmin($niveau), 403);

        return $next($request);
    }
}
