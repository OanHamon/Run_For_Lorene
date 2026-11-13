<?php

beforeEach(function () {
    $this->seed();
});

test('hors beta, aucun bandeau', function () {
    $this->get(route('home'))->assertOk()->assertDontSee('data-test="bandeau-beta"', false);
});

test('en beta, chaque espace affiche le bandeau « Version démo » avec le lien de feedback', function () {
    config(['rfl.beta' => true, 'rfl.beta_feedback' => 'https://forms.gle/avis-beta']);

    $this->get(route('home'))->assertOk()
        ->assertSee('Version démo')->assertSee('https://forms.gle/avis-beta')->assertDontSee('4242');

    $this->actingAs(admin(1))->get('/admin')->assertOk()->assertSee('Version démo');
});

test("en beta, l'état technique signale une clé Stripe live", function () {
    config(['rfl.beta' => true, 'services.stripe.secret' => 'sk_live_xxx']);

    $this->actingAs(admin(3))->get('/admin/technique')->assertOk()
        ->assertSee('Mode beta')->assertSee('les testeurs paieraient pour de vrai');
});
