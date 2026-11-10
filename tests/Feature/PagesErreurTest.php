<?php

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

beforeEach(fn () => $this->withoutVite());

test('la 404 est en français, aux couleurs de la charte', function () {
    $this->get('/cette-page-n-existe-pas')
        ->assertNotFound()
        ->assertSee('<html lang="fr">', false)
        ->assertSee('Page introuvable')
        ->assertSee('Revenir à l\'accueil', false)
        ->assertSee('data-test="page-erreur"', false)
        ->assertSee('logo-run-for-lorene.svg');
});

test('la 419 (jeton CSRF expiré) propose de recharger la page précédente', function () {
    // Le middleware CSRF est neutralisé pendant les tests : on lève directement l'exception qu'il produirait.
    Route::post('/test-419', fn () => throw new TokenMismatchException)->middleware('web');

    $this->from('/connexion')
        ->post('/test-419')
        ->assertStatus(419)
        ->assertSee('Page expirée')
        ->assertSee('Recharger la page')
        ->assertSee(url('/connexion'));
});

test('la 500 rassure sans détail technique', function () {
    config(['app.debug' => false]);
    Route::get('/test-500', fn () => throw new RuntimeException('détail interne'));

    $this->get('/test-500')
        ->assertStatus(500)
        ->assertSee('Un souci de notre côté')
        ->assertDontSee('détail interne');
});

test('la 503 de maintenance ne renvoie pas vers l\'accueil', function () {
    Route::get('/test-503', fn () => abort(503));

    $this->get('/test-503')
        ->assertStatus(503)
        ->assertSee('Petite pause technique')
        ->assertDontSee('Revenir à l\'accueil', false);
});

test('les autres erreurs retombent sur un gabarit en français', function (int $code, string $titre) {
    Route::get("/test-{$code}", fn () => abort($code));

    $this->get("/test-{$code}")
        ->assertStatus($code)
        ->assertSee($titre)
        ->assertSee("Erreur {$code}");
})->with([
    [401, 'Connexion nécessaire'],
    [403, 'Accès refusé'],
    [429, 'Trop de tentatives'],
    [410, 'Demande impossible'],
    [502, 'Un souci de notre côté'],
]);
