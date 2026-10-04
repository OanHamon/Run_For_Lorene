<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('espace'));

        // Stripe signe ses webhooks : pas de jeton CSRF possible sur cette route.
        $middleware->validateCsrfTokens(except: ['stripe/webhook']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
