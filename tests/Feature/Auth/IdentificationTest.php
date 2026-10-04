<?php

use App\Models\User;
use App\Notifications\CodeEmailNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;

beforeEach(function () {
    Notification::fake();
});

function codeEnvoyeA(User $user): string
{
    $code = null;
    Notification::assertSentTo($user, CodeEmailNotification::class, function ($n) use (&$code) {
        $code = $n->code;

        return true;
    });

    return $code;
}

test('la page de connexion est dans la charte et accessible aux invités', function () {
    $this->get('/connexion')->assertOk()->assertSee('Connecte-toi');
    $this->get('/login')->assertRedirect('/connexion');
});

test("un invité qui ouvre son espace est renvoyé vers la connexion maison", function () {
    $this->get('/espace')->assertRedirect(route('login'));
});

test('création de compte : le compte est connecté seulement après le code reçu par email', function () {
    $composant = Volt::test('auth.identification', ['destination' => route('inscription.tunnel')])
        ->set('email', '  Lea.Durand@Exemple.fr ')
        ->call('continuerEmail')
        ->assertSet('mode', 'creation')
        ->assertSet('email', 'lea.durand@exemple.fr')
        ->set('prenom', 'Léa')
        ->set('nom', 'Durand')
        ->set('password', 'motdepasse1')
        ->call('creerCompte')
        ->assertHasNoErrors()
        ->assertSet('mode', 'code');

    $user = User::where('email', 'lea.durand@exemple.fr')->first();
    expect($user->hasVerifiedEmail())->toBeFalse();
    $this->assertGuest();

    $composant->set('code', '000000')->call('validerCode')->assertHasErrors('code');
    $this->assertGuest();

    $composant->set('code', codeEnvoyeA($user))
        ->call('validerCode')
        ->assertRedirect(route('inscription.tunnel'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('connexion par mot de passe', function () {
    $user = User::factory()->create(['email' => 'camille@exemple.fr']);

    Volt::test('auth.identification')
        ->set('email', 'camille@exemple.fr')
        ->call('continuerEmail')
        ->assertSet('mode', 'password')
        ->set('password', 'password')
        ->call('seConnecter')
        ->assertRedirect(route('espace'));

    $this->assertAuthenticatedAs($user);
});

test('mauvais mot de passe refusé, puis blocage après 5 essais', function () {
    User::factory()->create(['email' => 'camille@exemple.fr']);

    $composant = Volt::test('auth.identification')
        ->set('email', 'camille@exemple.fr')
        ->call('continuerEmail');

    foreach (range(1, 5) as $_) {
        $composant->set('password', 'faux')->call('seConnecter')->assertHasErrors('password');
    }

    $composant->set('password', 'password')->call('seConnecter')->assertHasErrors('password');
    $this->assertGuest();
});

test("un compte non vérifié doit saisir le code même avec le bon mot de passe", function () {
    $user = User::factory()->create(['email' => 'nouveau@exemple.fr', 'email_verifie_le' => null]);

    $composant = Volt::test('auth.identification')
        ->set('email', 'nouveau@exemple.fr')
        ->call('continuerEmail')
        ->set('password', 'password')
        ->call('seConnecter')
        ->assertSet('mode', 'code');

    $this->assertGuest();

    $composant->set('code', codeEnvoyeA($user))->call('validerCode')->assertRedirect(route('espace'));
    $this->assertAuthenticatedAs($user);
});

test('mot de passe oublié : connexion par code', function () {
    $user = User::factory()->create(['email' => 'camille@exemple.fr']);

    $composant = Volt::test('auth.identification')
        ->set('email', 'camille@exemple.fr')
        ->call('continuerEmail')
        ->call('recevoirCode')
        ->assertSet('mode', 'code');

    $composant->set('code', codeEnvoyeA($user))->call('validerCode')->assertRedirect(route('espace'));
    $this->assertAuthenticatedAs($user);
});

test('le code est invalidé après 5 essais ratés', function () {
    $user = User::factory()->create(['email' => 'camille@exemple.fr']);

    $composant = Volt::test('auth.identification')
        ->set('email', 'camille@exemple.fr')
        ->call('continuerEmail')
        ->call('recevoirCode');

    $bon = codeEnvoyeA($user);
    $faux = $bon === '111111' ? '222222' : '111111';

    foreach (range(1, 5) as $_) {
        $composant->set('code', $faux)->call('validerCode');
    }

    $composant->set('code', $bon)->call('validerCode')->assertHasErrors('code');
    $this->assertGuest();
});

test("le renvoi du code est limité dans le temps", function () {
    $user = User::factory()->create(['email' => 'camille@exemple.fr']);

    Volt::test('auth.identification')
        ->set('email', 'camille@exemple.fr')
        ->call('continuerEmail')
        ->call('recevoirCode')
        ->call('renvoyerCode')
        ->assertHasErrors('code');

    Notification::assertSentToTimes($user, CodeEmailNotification::class, 1);
});

test('un compte connecté mais non vérifié est envoyé vers la saisie du code', function () {
    $user = User::factory()->create(['email_verifie_le' => null]);

    $this->actingAs($user)->get('/espace')->assertRedirect(route('verification.notice'));
    $this->actingAs($user)->get('/verification-email')->assertOk()->assertSee('Vérifie tes emails');
    Notification::assertSentTo($user, CodeEmailNotification::class);
});

test('déconnexion', function () {
    $this->actingAs(User::factory()->create())->post('/deconnexion')->assertRedirect('/');
    $this->assertGuest();
});
