<?php

use Illuminate\Support\Facades\Blade;

it('cache la page de mise au point TRACK hors du local', function () {
    $this->get('/dev/track')->assertNotFound();
});

it('affiche la page de mise au point TRACK en local', function () {
    $this->app['env'] = 'local';

    $this->get('/dev/track')
        ->assertOk()
        ->assertSee('data-track', false)
        ->assertSee('data-graine="demo-jade"', false)
        ->assertSee('data-code', false);
});

it("transmet le code du billet à l'empreinte", function () {
    $html = Blade::render('<x-track.empreinte code="RFL-12-0123456789abcdef0123" />');

    expect($html)
        ->toContain('data-code="RFL-12-0123456789abcdef0123"')
        ->toContain('data-graine="RFL-12-0123456789abcdef0123"')
        ->toContain('code du billet');
});
