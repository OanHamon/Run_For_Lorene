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
        ->assertSee('data-graine="demo-jade"', false);
});

it('rend une empreinte avec sa graine, statique sur demande', function () {
    $html = Blade::render('<x-track.empreinte graine="RFL-12-abc" statique class="w-24" />');

    expect($html)
        ->toContain('data-graine="RFL-12-abc"')
        ->toContain('data-statique')
        ->toContain('w-24')
        ->toContain('role="img"');
});
