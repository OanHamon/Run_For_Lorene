<?php

use App\Http\Middleware\ConfirmationAdmin;
use App\Models\User;
use App\Notifications\CodeEmailNotification;
use App\Services\Auth\CodeEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

/** Dernier code envoyé par email à $user. */
function dernierCode(User $user): string
{
    return Notification::sent($user, CodeEmailNotification::class)->last()->code;
}

/** Étape 1 de la connexion admin (mot de passe « password » des factories). */
function motDePasseAdmin(User $user, string $mdp = 'password')
{
    return test()->post('/admin/connexion', ['email' => $user->email, 'mot_de_passe' => $mdp]);
}

test('sans connexion admin, le back-office renvoie vers sa propre connexion', function () {
    $this->get('/admin/inscriptions?evenement=1')->assertRedirect('/admin/connexion');
    $this->get('/admin/connexion')->assertOk()->assertSee('Administration')->assertSee('Connexion participant');
});

test("mot de passe puis code : l'admin arrive sur la page demandée", function () {
    $admin = admin(3, confirme: false);
    $this->get('/admin/inscriptions?evenement=1');

    motDePasseAdmin($admin)->assertRedirect('/admin/connexion/code');
    $this->get('/admin/connexion/code')->assertOk()->assertSee('Confirme ton accès')->assertSee($admin->email);
    Notification::assertSentToTimes($admin, CodeEmailNotification::class, 1);
    expect(Notification::sent($admin, CodeEmailNotification::class)->last()->usage)->toBe(CodeEmail::ADMIN);
    $this->assertGuest('admin');

    $ancienneSession = session()->getId();
    $this->post('/admin/connexion/code', ['code' => dernierCode($admin)])
        ->assertRedirect(url('/admin/inscriptions?evenement=1'))
        ->assertSessionHas(ConfirmationAdmin::CLE);

    expect(session()->getId())->not->toBe($ancienneSession);
    $this->assertAuthenticatedAs($admin, 'admin');
    $this->assertGuest('web');
    $this->get('/admin/inscriptions')->assertOk();
});

test('sans page demandée, la connexion mène au tableau de bord', function () {
    $admin = admin(1, confirme: false);
    motDePasseAdmin($admin);
    $this->get('/admin/connexion/code');

    $this->post('/admin/connexion/code', ['code' => dernierCode($admin)])->assertRedirect('/admin');
});

test('le mot de passe seul ne connecte pas et ne mène pas au code sans être vérifié', function () {
    $admin = admin(3, confirme: false);

    motDePasseAdmin($admin, 'mauvais')->assertSessionHasErrors('email');
    $this->get('/admin/connexion/code')->assertRedirect('/admin/connexion');
    $this->post('/admin/connexion/code', ['code' => '123456'])->assertRedirect('/admin/connexion');
    Notification::assertNothingSentTo($admin);
    $this->get('/admin')->assertRedirect('/admin/connexion');
});

test('un compte participant ne passe pas la connexion admin, avec la même réponse qu\'un compte inconnu', function () {
    $user = User::factory()->create();

    motDePasseAdmin($user)->assertSessionHasErrors(['email' => 'Email ou mot de passe incorrect.']);
    $this->post('/admin/connexion', ['email' => 'inconnu@exemple.fr', 'mot_de_passe' => 'password'])
        ->assertSessionHasErrors(['email' => 'Email ou mot de passe incorrect.']);

    $this->get('/admin/connexion/code')->assertRedirect('/admin/connexion');
    Notification::assertNothingSentTo($user);
});

test('un participant connecté dans son espace ne peut pas entrer dans le back-office', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin')->assertRedirect('/admin/connexion');
});

test('les essais de mot de passe sont limités', function () {
    $admin = admin(3, confirme: false);

    foreach (range(1, 5) as $_) {
        motDePasseAdmin($admin, 'mauvais');
    }

    motDePasseAdmin($admin)->assertSessionHasErrors('email');
    $this->get('/admin/connexion/code')->assertRedirect('/admin/connexion');
});

test('un mauvais code est refusé', function () {
    $admin = admin(3, confirme: false);
    motDePasseAdmin($admin);
    $this->get('/admin/connexion/code');
    $faux = dernierCode($admin) === '000000' ? '111111' : '000000';

    $this->from('/admin/connexion/code')->post('/admin/connexion/code', ['code' => $faux])
        ->assertRedirect('/admin/connexion/code')
        ->assertSessionHasErrors('code')
        ->assertSessionMissing(ConfirmationAdmin::CLE);

    $this->assertGuest('admin');
    $this->get('/admin')->assertRedirect('/admin/connexion');
});

test('le code est bloqué après 5 essais ratés', function () {
    $admin = admin(3, confirme: false);
    motDePasseAdmin($admin);
    $this->get('/admin/connexion/code');
    $code = dernierCode($admin);
    $faux = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $_) {
        $this->post('/admin/connexion/code', ['code' => $faux]);
    }

    $this->post('/admin/connexion/code', ['code' => $code])->assertSessionHasErrors('code');
    $this->assertGuest('admin');
});

test("l'étape du code expire avec le code", function () {
    $admin = admin(3, confirme: false);
    motDePasseAdmin($admin);
    $this->get('/admin/connexion/code');
    $code = dernierCode($admin);

    $this->travel(CodeEmail::VALIDITE_MINUTES + 1)->minutes();

    $this->post('/admin/connexion/code', ['code' => $code])->assertRedirect('/admin/connexion');
    $this->assertGuest('admin');
});

test('un code de connexion participant ne vaut pas code admin', function () {
    $admin = admin(3, confirme: false);
    app(CodeEmail::class)->envoyer($admin); // code de connexion participant
    $codeConnexion = dernierCode($admin);

    motDePasseAdmin($admin);
    $this->get('/admin/connexion/code');
    expect(Notification::sent($admin, CodeEmailNotification::class)->last()->usage)->toBe(CodeEmail::ADMIN);

    $this->post('/admin/connexion/code', ['code' => $codeConnexion])->assertSessionHasErrors('code');
    $this->assertGuest('admin');
});

test('une session admin de plus de 8 heures est fermée', function () {
    $this->actingAs(admin(3, confirme: false));

    adminConfirme(now()->subHours(7));
    $this->get('/admin')->assertOk();

    adminConfirme(now()->subHours(8)->subMinute());
    $this->get('/admin')->assertRedirect('/admin/connexion');
    $this->assertGuest('admin');
});

test('une session admin sans code validé est fermée', function () {
    $this->actingAs(admin(3, confirme: false))
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('/admin')
        ->assertRedirect('/admin/connexion');
});

test('renvoyer le code respecte le délai entre deux envois', function () {
    $admin = admin(3, confirme: false);
    motDePasseAdmin($admin);
    $this->get('/admin/connexion/code');

    $this->from('/admin/connexion/code')->post('/admin/connexion/code/renvoyer')->assertSessionHasErrors('code');
    Notification::assertSentToTimes($admin, CodeEmailNotification::class, 1);

    $this->travel(61)->seconds();
    $this->post('/admin/connexion/code/renvoyer')->assertSessionHas('statut');
    Notification::assertSentToTimes($admin, CodeEmailNotification::class, 2);
});

test('un accès retiré pendant la session la ferme', function () {
    $this->actingAs($admin = admin(3));
    $admin->admin()->delete();

    $this->get('/admin')->assertRedirect('/admin/connexion');
    $this->assertGuest('admin');
});

test('se déconnecter ferme la session admin', function () {
    $this->actingAs(admin(3));

    $this->post('/admin/deconnexion')->assertRedirect('/admin/connexion');
    $this->assertGuest('admin');
    $this->get('/admin')->assertRedirect('/admin/connexion');
});

test("le back-office n'a aucun lien vers l'espace participant, et inversement", function () {
    $this->actingAs(admin(3));
    $this->get('/admin')->assertOk()->assertDontSee(route('espace'))->assertSee(route('admin.deconnexion'));

    Auth::guard('admin')->logout();
    $this->actingAs($user = User::factory()->create());
    $this->get('/espace')->assertOk()->assertDontSee('Administration');
});
