<?php

use App\Http\Middleware\CompteParticipant;
use App\Http\Middleware\ConfirmationAdmin;
use App\Http\Middleware\EntetesSecurite;
use App\Http\Middleware\NiveauAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Back-office : garde « admin », distincte de l'espace participant (garde « web »).
            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin-connexion.php'));

            Route::middleware(['web', 'auth:admin', 'admin', 'admin.confirme'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => NiveauAdmin::class,
            'admin.confirme' => ConfirmationAdmin::class,
        ]);

        $middleware->append(EntetesSecurite::class);
        $middleware->web(append: CompteParticipant::class);

        $estAdmin = fn ($request) => $request->is('admin', 'admin/*');
        $middleware->redirectGuestsTo(fn ($request) => $estAdmin($request) ? route('admin.connexion') : route('login'));
        $middleware->redirectUsersTo(fn ($request) => $estAdmin($request) ? route('admin.tableau') : route('espace'));

        // Stripe signe ses webhooks : pas de jeton CSRF possible sur cette route.
        $middleware->validateCsrfTokens(except: ['stripe/webhook']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
