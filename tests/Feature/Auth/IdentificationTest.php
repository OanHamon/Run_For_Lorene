<?php

use App\Models\User;
use App\Notifications\CodeEmailNotification;
use App\Services\Auth\CodeEmail;
use Illuminate\Support\Facades\Hash;
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

test("après 5 essais ratés, on ne peut pas redemander un code aussitôt (pas de force brute)", function () {
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

    $composant->call('recevoirCode')->assertHasErrors('code');

    Notification::assertSentToTimes($user, CodeEmailNotification::class, 1);
});

test("le quota de codes par heure est respecté", function () {
    $user = User::factory()->create(['email' => 'camille@exemple.fr']);
    $codes = app(CodeEmail::class);

    foreach (range(1, CodeEmail::ENVOIS_MAX_PAR_HEURE) as $_) {
        expect($codes->envoyer($user))->toBeTrue();
        $this->travel(CodeEmail::DELAI_RENVOI_SECONDES + 1)->seconds();
    }

    expect($codes->envoyer($user))->toBeFalse();
    Notification::assertSentToTimes($user, CodeEmailNotification::class, CodeEmail::ENVOIS_MAX_PAR_HEURE);
});

test("changer la casse de l'email ne contourne pas la limite d'essais de mot de passe", function () {
    User::factory()->create(['email' => 'camille@exemple.fr']);

    $composant = Volt::test('auth.identification')
        ->set('email', 'camille@exemple.fr')
        ->call('continuerEmail');

    foreach (['Camille@exemple.fr', 'CAMILLE@exemple.fr', 'cAmille@exemple.fr', 'caMille@exemple.fr', 'camIlle@exemple.fr'] as $variante) {
        $composant->set('email', $variante)->set('password', 'faux')->call('seConnecter');
    }

    $composant->set('email', 'CamillE@exemple.fr')->set('password', 'password')->call('seConnecter')->assertHasErrors('password');
    $this->assertGuest();
});

test("un compte créé par un tiers avec mon adresse : son mot de passe est effacé quand je vérifie l'adresse", function () {
    // Le tiers crée le compte avec l'adresse de la victime, sans pouvoir la vérifier.
    Volt::test('auth.identification')
        ->set('email', 'victime@exemple.fr')->call('continuerEmail')
        ->set('prenom', 'Pirate')->set('nom', 'X')->set('password', 'motdepassedutiers')
        ->call('creerCompte');

    $user = User::where('email', 'victime@exemple.fr')->first();
    $this->travel(CodeEmail::DELAI_RENVOI_SECONDES + 1)->seconds();

    // La victime se connecte par code (« mot de passe oublié »).
    $victime = Volt::test('auth.identification')
        ->set('email', 'victime@exemple.fr')->call('continuerEmail')
        ->assertSet('mode', 'password')
        ->call('renvoyerCode');

    $code = null;
    Notification::assertSentTo($user, CodeEmailNotification::class, function ($n) use (&$code) {
        $code = $n->code;

        return true;
    });

    $victime->set('code', $code)->call('validerCode')->assertRedirect(route('espace'));
    expect($user->fresh()->mot_de_passe)->toBeNull();

    // Le tiers ne peut plus utiliser son mot de passe.
    auth()->logout();
    Volt::test('auth.identification')
        ->set('email', 'victime@exemple.fr')->call('continuerEmail')
        ->assertSet('mode', 'code');
    $this->assertGuest();
});

test("le titulaire qui a créé son compte garde son mot de passe après le code", function () {
    $composant = Volt::test('auth.identification')
        ->set('email', 'lea@exemple.fr')->call('continuerEmail')
        ->set('prenom', 'Léa')->set('nom', 'Durand')->set('password', 'motdepasse1')
        ->call('creerCompte');

    $user = User::where('email', 'lea@exemple.fr')->first();
    $composant->set('code', codeEnvoyeA($user))->call('validerCode')->assertRedirect(route('espace'));

    expect(Hash::check('motdepasse1', $user->fresh()->mot_de_passe))->toBeTrue();
});

test("la page « vérifie ton email » renvoie un compte déjà vérifié vers son espace", function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/verification-email')->assertRedirect(route('espace'));
    Notification::assertNothingSent();
});

test("mot de passe oublié : après connexion par code, on redéfinit son mot de passe sans l'ancien", function () {
    $user = User::factory()->create(['email' => 'camille@exemple.fr']);

    $composant = Volt::test('auth.identification')
        ->set('email', 'camille@exemple.fr')->call('continuerEmail')->call('recevoirCode');
    $composant->set('code', codeEnvoyeA($user))->call('validerCode');

    Volt::test('espace.compte')
        ->set('nouveau', 'nouveaumotdepasse')
        ->set('nouveau_confirmation', 'nouveaumotdepasse')
        ->call('enregistrerMotDePasse')
        ->assertHasNoErrors();

    expect(Hash::check('nouveaumotdepasse', $user->fresh()->mot_de_passe))->toBeTrue();
});

test("après une connexion par mot de passe, le mot de passe actuel reste exigé", function () {
    $user = User::factory()->create(['email' => 'camille@exemple.fr']);

    Volt::test('auth.identification')
        ->set('email', 'camille@exemple.fr')->call('continuerEmail')
        ->set('password', 'password')->call('seConnecter');

    Volt::test('espace.compte')
        ->set('nouveau', 'nouveaumotdepasse')
        ->set('nouveau_confirmation', 'nouveaumotdepasse')
        ->call('enregistrerMotDePasse')
        ->assertHasErrors('actuel');
});
