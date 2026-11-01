<?php

use App\Models\Course;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\User;
use App\Support\Billet;

beforeEach(function () {
    $this->seed();
    $this->evenement = Evenement::courant();
    $course = Course::where('id_evenement', $this->evenement->id_evenement)->first();
    $this->inscription = inscriptionPayee(User::factory()->create(['prenom' => 'Jade', 'nom' => 'Rivière']), $course);
    $this->inscription->update(['dossard' => 1042]);
});

test("le banc d'essai des QR n'existe pas hors du local", function () {
    $this->get('/dev/qr')->assertNotFound();
    $this->postJson('/dev/qr/verifier', ['code' => Billet::jeton($this->inscription)])->assertNotFound();
});

test("le banc d'essai montre de vrais billets et des codes piégés", function () {
    $this->app['env'] = 'local';

    $this->get('/dev/qr')->assertOk()
        ->assertSee('Billets valides')
        ->assertSee('Codes piégés')
        ->assertSee('Signature fausse')
        ->assertSee('Plein écran')
        ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()')
        ->assertSee('data-code', false)
        ->assertSee('<svg', false);
});

test('le lecteur reconnaît un vrai billet et refuse les faux', function () {
    $this->app['env'] = 'local';
    // En « local », la protection CSRF s'applique comme sur la vraie page.
    $this->withSession(['_token' => 'jeton-csrf'])->withHeader('X-CSRF-TOKEN', 'jeton-csrf');
    $jeton = Billet::jeton($this->inscription);

    $this->postJson('/dev/qr/verifier', ['code' => $jeton])->assertOk()
        ->assertJson(['ok' => true, 'fiche' => ['nom' => 'Jade Rivière', 'dossard' => 1042]]);

    $this->postJson('/dev/qr/verifier', ['code' => substr($jeton, 0, -1).'x'])->assertJson(['ok' => false])
        ->assertJsonMissingPath('fiche');
    $this->postJson('/dev/qr/verifier', ['code' => 'https://example.org'])->assertJson(['ok' => false]);

    $this->inscription->update(['statut' => Inscription::ANNULEE]);
    $this->postJson('/dev/qr/verifier', ['code' => $jeton])->assertJson(['ok' => false])
        ->assertJsonPath('message', 'Billet authentique, mais inscription annulée.');
});
