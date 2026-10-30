<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Espace participant et back-office sont séparés : un compte admin n'a pas d'espace
 * participant. Une session participant d'un compte devenu admin (ou rouverte par le cookie
 * « se souvenir de moi ») est fermée, et la personne renvoyée vers la connexion admin.
 */
class CompteParticipant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('web')->user()?->admin()->exists()) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->regenerate();

        if ($request->isMethod('GET') && ! $request->expectsJson()) {
            return redirect()->route('admin.connexion')
                ->with('statut', 'Ce compte est un compte admin : connecte-toi depuis l\'administration.');
        }

        return $next($request);
    }
}
