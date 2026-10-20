<?php

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;

test('les pages portent les en-têtes de sécurité', function () {
    $this->get('/connexion')->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'; base-uri 'self'; object-src 'none'")
        ->assertHeaderMissing('Strict-Transport-Security');

    $this->get('https://localhost/connexion')->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});

test("derrière un proxy de confiance, l'IP du visiteur est celle transmise par le proxy", function () {
    config(['rfl.proxies_de_confiance' => '10.0.0.1']);
    (new App\Providers\AppServiceProvider(app()))->boot();
    Route::get('/test-ip', fn () => request()->ip())->middleware(TrustProxies::class);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->withHeader('X-Forwarded-For', '203.0.113.7')
        ->get('/test-ip')
        ->assertSeeText('203.0.113.7');

    TrustProxies::flushState();
});

test('les anciennes pages « settings » du starter kit mènent à « Mon compte »', function () {
    $this->get('/settings/profile')->assertRedirect('/espace/compte');
    $this->get('/settings')->assertRedirect('/espace/compte');
});
