<?php

use App\Services\Reveil;
use App\Support\FileSansWorker;
use App\Support\TachesPlanifiees;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
 * Hébergement mutualisé (OVH Personal) : pas de worker, cron au mieux une fois par heure.
 * Les emails partent juste après la réponse, un réveil externe lance les tâches planifiées.
 */

beforeEach(function () {
    config(['queue.default' => 'database']);
});

test('un email mis en file part juste après la réponse, sans worker', function () {
    config(['rfl.file_sans_worker' => true]);

    dispatch(function () {
        Cache::put('envoye', true);
    });
    expect(DB::table('jobs')->count())->toBe(1);

    app()->terminate(); // fin de la requête : la réponse est déjà partie

    expect(Cache::get('envoye'))->toBeTrue()
        ->and(DB::table('jobs')->count())->toBe(0);
});

test('sans le réglage, la file attend un worker', function () {
    config(['rfl.file_sans_worker' => false]);

    dispatch(function () {
        Cache::put('envoye', true);
    });
    app()->terminate();

    expect(Cache::get('envoye'))->toBeNull()
        ->and(DB::table('jobs')->count())->toBe(1);
});

test('un seul passage à la fois : une requête laisse ses emails au passage en cours', function () {
    $verrou = Cache::lock('file-sans-worker', 60);
    $verrou->get();

    dispatch(function () {
        Cache::put('envoye', true);
    });

    expect(FileSansWorker::vider())->toBeFalse()
        ->and(DB::table('jobs')->count())->toBe(1);

    $verrou->release();
    expect(FileSansWorker::vider())->toBeTrue()
        ->and(Cache::get('envoye'))->toBeTrue();
});

test("l'adresse de réveil n'existe pas sans la bonne clé", function () {
    config(['rfl.reveil_cle' => null]);
    $this->get('/reveil/nimportequoi')->assertNotFound();

    config(['rfl.reveil_cle' => 'cle-secrete-de-test']);
    $this->get('/reveil/mauvaise-cle')->assertNotFound();

    $this->get('/reveil/cle-secrete-de-test')->assertOk()->assertJson(['ok' => true])
        ->assertHeader('Cache-Control', 'no-store, private');
    expect(Cache::get(Reveil::DERNIER))->not->toBeNull();
});

test('le réveil vide la file des emails', function () {
    config(['rfl.reveil_cle' => 'cle-secrete-de-test']);
    dispatch(function () {
        Cache::put('envoye', true);
    });

    $this->get('/reveil/cle-secrete-de-test')->assertOk()->assertJson(['file' => true]);

    expect(Cache::get('envoye'))->toBeTrue();
});

test('le réveil lance une tâche à échéance une seule fois, et rattrape une échéance manquée', function () {
    $this->travelTo(now()->setTime(3, 37)); // le réveil de 3 h 00 a été manqué

    // Tout a tourné récemment, sauf la purge de 3 h : son dernier passage date d'hier.
    foreach (['rfl:relancer-documents', 'rfl:abandonner-commandes', 'rfl:envois-programmes'] as $nom) {
        TachesPlanifiees::noterNom($nom, true);
    }
    $this->travel(-1)->days();
    TachesPlanifiees::noterNom('rfl:purger-documents', true);
    $this->travel(1)->days();

    $premier = app(Reveil::class)();
    $second = app(Reveil::class)();

    expect($premier['taches'])->toBe(['rfl:purger-documents' => true])
        ->and($second['taches'])->toBe([])
        ->and(TachesPlanifiees::derniere('rfl:purger-documents')['le'])->toBe(now()->toIso8601String());
});

test("l'état technique signale un réveil arrêté en production", function () {
    config(['rfl.reveil_cle' => 'cle-secrete-de-test', 'rfl.file_sans_worker' => true]);
    app()->detectEnvironment(fn () => 'production');
    Cache::put(Reveil::DERNIER, now()->subHour()->toIso8601String());

    $this->actingAs(admin(3))->get('/admin/technique')->assertOk()
        ->assertSee('Réveil du site')
        ->assertSee('dernier passage il y a 1 heure');
});
