<?php

use App\Http\Middleware\ConfirmationAdmin;
use App\Models\User;
use App\Notifications\CodeEmailNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => Notification::fake());

/** Dernier code envoyé par email à $user. */
function dernierCode(User $user): string
{
    return Notification::sent($user, CodeEmailNotification::class)->last()->code;
}

test('un admin connecté sans confirmation est renvoyé vers la page de code', function () {
    $this->actingAs($admin = admin(3, confirme: false))
        ->get('/admin/inscriptions?evenement=1')
        ->assertRedirect('/admin/confirmation');

    $this->get('/admin/confirmation')->assertOk()->assertSee('Confirme ton accès')->assertSee($admin->email);
    Notification::assertSentToTimes($admin, CodeEmailNotification::class, 1);
});

test("avec le bon code, l'admin arrive sur la page demandée", function () {
    $this->actingAs($admin = admin(3, confirme: false))->get('/admin/inscriptions?evenement=1');
    $this->get('/admin/confirmation');
    $ancienneSession = session()->getId();

    $this->post('/admin/confirmation', ['code' => dernierCode($admin)])
        ->assertRedirect('/admin/inscriptions?evenement=1')
        ->assertSessionHas(ConfirmationAdmin::CLE);

    expect(session()->getId())->not->toBe($ancienneSession);
    $this->get('/admin/inscriptions')->assertOk();
});

test('sans page demandée, la confirmation mène au tableau de bord', function () {
    $this->actingAs($admin = admin(1, confirme: false))->get('/admin/confirmation');

    $this->post('/admin/confirmation', ['code' => dernierCode($admin)])->assertRedirect('/admin');
});

test('un mauvais code est refusé', function () {
    $this->actingAs($admin = admin(3, confirme: false))->get('/admin/confirmation');
    $faux = dernierCode($admin) === '000000' ? '111111' : '000000';

    $this->from('/admin/confirmation')->post('/admin/confirmation', ['code' => $faux])
        ->assertRedirect('/admin/confirmation')
        ->assertSessionHasErrors('code')
        ->assertSessionMissing(ConfirmationAdmin::CLE);

    $this->get('/admin')->assertRedirect('/admin/confirmation');
});

test('le code est bloqué après 5 essais ratés', function () {
    $this->actingAs($admin = admin(3, confirme: false))->get('/admin/confirmation');
    $code = dernierCode($admin);
    $faux = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $_) {
        $this->post('/admin/confirmation', ['code' => $faux]);
    }

    $this->post('/admin/confirmation', ['code' => $code])->assertSessionHasErrors('code');
    $this->get('/admin')->assertRedirect('/admin/confirmation');
});

test('une confirmation de plus de 8 heures est redemandée', function () {
    $this->actingAs(admin(3, confirme: false));

    adminConfirme(now()->subHours(7));
    $this->get('/admin')->assertOk();

    adminConfirme(now()->subHours(8)->subMinute());
    $this->get('/admin')->assertRedirect('/admin/confirmation');
});

test('une connexion par code ne dispense pas de la confirmation', function () {
    $this->actingAs(admin(3, confirme: false))
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('/admin')
        ->assertRedirect('/admin/confirmation');
});

test('renvoyer le code respecte le délai entre deux envois', function () {
    $this->actingAs($admin = admin(3, confirme: false))->get('/admin/confirmation');

    $this->from('/admin/confirmation')->post('/admin/confirmation/renvoyer')->assertSessionHasErrors('code');
    Notification::assertSentToTimes($admin, CodeEmailNotification::class, 1);

    $this->travel(61)->seconds();
    $this->post('/admin/confirmation/renvoyer')->assertSessionHas('statut');
    Notification::assertSentToTimes($admin, CodeEmailNotification::class, 2);
});

test('un non-admin reçoit une 403 et aucun code', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/admin')->assertForbidden();
    $this->get('/admin/confirmation')->assertForbidden();
    $this->post('/admin/confirmation/renvoyer')->assertForbidden();

    Notification::assertNothingSentTo($user);
});
