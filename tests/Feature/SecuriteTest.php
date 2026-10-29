<?php

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Volt\Volt;

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
    (new AppServiceProvider(app()))->boot();
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

test("pied de page : plus de lien mort, pages d'information servies ou « pas encore en ligne »", function () {
    $this->seed();

    $this->get('/')->assertOk()->assertDontSee('href="#"', false)
        ->assertSee(route('pages.show', 'mentions-legales'), false)
        ->assertSee(route('textes.show', 'rgpd'), false);

    $this->get(route('pages.show', 'cookies'))->assertOk()->assertSee('ne demandent pas ton accord');
    $this->get(route('pages.show', 'mentions-legales'))->assertNotFound()->assertSee("n'est pas encore en ligne", false);
    $this->get('/infos/inconnue')->assertNotFound();
});

test("lien d'activation : un identifiant inconnu donne la même page qu'un lien faux", function () {
    $existant = User::factory()->create();

    $this->get("/activation/{$existant->id_user}")->assertOk()->assertSee('lien', false);
    $this->get('/activation/999999')->assertOk();
    $this->get('/activation/abc')->assertOk();
});

test("tunnel : l'étape ne se change pas depuis le navigateur", function () {
    $this->seed();
    $this->actingAs(User::factory()->create());

    expect(fn () => Volt::test('inscription.tunnel')->set('etape', 'recap'))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

test('« Mon compte » : le mot de passe actuel est limité à 5 essais par minute', function () {
    $user = User::factory()->create(['mot_de_passe' => 'bon-mot-de-passe']);
    $this->actingAs($user);

    $compte = Volt::test('espace.compte');
    foreach (range(1, 5) as $_) {
        $compte->set('actuel', 'faux')->set('nouveau', 'nouveau-mdp-123')->set('nouveau_confirmation', 'nouveau-mdp-123')
            ->call('enregistrerMotDePasse')->assertHasErrors('actuel');
    }

    $compte->set('actuel', 'bon-mot-de-passe')->call('enregistrerMotDePasse');
    expect($compte->errors()->first('actuel'))->toContain("Trop d'essais");
    expect(Hash::check('bon-mot-de-passe', $user->fresh()->mot_de_passe))->toBeTrue();
});
