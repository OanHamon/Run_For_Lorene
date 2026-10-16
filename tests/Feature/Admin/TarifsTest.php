<?php

use App\Models\Course;
use App\Models\Evenement;
use App\Models\Tarif;
use App\Services\Admin\ControleTarifs;

/*
 * Garde-fous des tarifs : période dans celle des inscriptions, pas de recouvrement
 * (mêmes jours et mêmes âges), tranche d'âge compatible avec la course, couverture complète
 * exigée pour publier.
 */

beforeEach(function () {
    // Inscriptions du 1er au 30 du mois prochain, événement le surlendemain.
    $this->ouverture = today()->addMonth()->startOfMonth();
    $this->fermeture = $this->ouverture->copy()->addDays(29);
    $this->evenement = Evenement::create([
        'nom' => 'Édition test',
        'date_evenement' => $this->fermeture->copy()->addDays(2),
        'ouverture' => $this->ouverture,
        'fermeture' => $this->fermeture->copy()->setTime(23, 59),
        'statut' => Evenement::BROUILLON,
    ]);
    $this->course = Course::create(['nom' => '5 km', 'heure_depart' => '10:00', 'age_min' => 6, 'id_evenement' => $this->evenement->id_evenement]);
    $this->jour = fn (int $n) => $this->ouverture->copy()->addDays($n)->toDateString();
    $this->tarif = fn (array $d = []) => [
        'libelle' => 'Plein tarif',
        'montant' => '10',
        'valide_du' => ($this->jour)(0),
        'valide_au' => ($this->jour)(29),
        'age_min' => '',
        'age_max' => '',
        ...$d,
    ];
    // Textes obligatoires publiés : seuls les tarifs peuvent bloquer la publication ici.
    foreach (App\Models\TexteLegal::OBLIGATOIRES as $type) {
        App\Models\TexteLegal::create(['type_texte' => $type, 'version' => 1, 'date_publication' => today(), 'fichier' => "textes/{$type}.pdf", 'id_evenement' => $this->evenement->id_evenement]);
    }
    $this->actingAs(admin(3));
});

test("un tarif ne commence pas avant l'ouverture ni ne finit après la fermeture", function () {
    $url = "/admin/courses/{$this->course->id_course}/tarifs";

    $this->post($url, ($this->tarif)(['valide_du' => $this->ouverture->copy()->subDay()->toDateString()]))->assertSessionHasErrorsIn('tarif', 'valide_du');
    $this->post($url, ($this->tarif)(['valide_au' => $this->fermeture->copy()->addDay()->toDateString()]))->assertSessionHasErrorsIn('tarif', 'valide_au');
    $this->post($url, ($this->tarif)(['valide_du' => ($this->jour)(10), 'valide_au' => ($this->jour)(5)]))->assertSessionHasErrorsIn('tarif', 'valide_au');
    $this->post($url, ($this->tarif)())->assertSessionHasNoErrors();

    expect($this->course->tarifs()->count())->toBe(1);
});

test('deux tarifs enfant et adulte sur la même période sont acceptés', function () {
    $url = "/admin/courses/{$this->course->id_course}/tarifs";

    $this->post($url, ($this->tarif)(['libelle' => 'Enfant', 'age_max' => '17']))->assertSessionHasNoErrors();
    $this->post($url, ($this->tarif)(['libelle' => 'Adulte', 'age_min' => '18']))->assertSessionHasNoErrors();

    expect($this->course->tarifs()->count())->toBe(2)
        ->and(app(ControleTarifs::class)->trous($this->course->fresh()))->toBeEmpty();
});

test('deux tarifs qui visent les mêmes âges les mêmes jours sont refusés', function () {
    $url = "/admin/courses/{$this->course->id_course}/tarifs";
    $this->post($url, ($this->tarif)(['libelle' => 'Early', 'valide_au' => ($this->jour)(9)]));

    // Chevauche Early d'un jour (le 10e jour) : refusé, avec le nom du tarif en conflit.
    $this->post($url, ($this->tarif)(['valide_du' => ($this->jour)(9)]))
        ->assertSessionHasErrorsIn('tarif', 'valide_du');
    expect(session('errors')->getBag('tarif')->first('valide_du'))->toContain('« Early »');

    // Le lendemain : accepté.
    $this->post($url, ($this->tarif)(['valide_du' => ($this->jour)(10)]))->assertSessionHasNoErrors();

    // Un tarif adulte qui recoupe un tarif « tous âges » est refusé aussi.
    $this->post($url, ($this->tarif)(['libelle' => 'Adulte', 'age_min' => '18', 'valide_du' => ($this->jour)(20)]))->assertSessionHasErrorsIn('tarif', 'valide_du');
});

test("modifier un tarif ne le compare pas à lui-même", function () {
    $tarif = $this->course->tarifs()->create(($this->tarif)(['age_min' => null, 'age_max' => null]));

    $this->put("/admin/tarifs/{$tarif->id_tarif}", ($this->tarif)(['montant' => '12']))->assertSessionHasNoErrors();
    expect($tarif->fresh()->montant)->toBe('12.00');
});

test("une tranche d'âge hors de celle de la course est refusée", function () {
    // La course commence à 6 ans : un tarif « jusqu'à 5 ans » ne servirait jamais.
    $this->post("/admin/courses/{$this->course->id_course}/tarifs", ($this->tarif)(['age_max' => '5']))
        ->assertSessionHasErrorsIn('tarif', 'age_min');
});

test('les trous de tarifs sont détectés par période et par âge', function () {
    $controle = app(ControleTarifs::class);
    $this->course->tarifs()->create(($this->tarif)(['libelle' => 'Early', 'valide_au' => ($this->jour)(9), 'age_min' => null, 'age_max' => null]));
    $this->course->tarifs()->create(($this->tarif)(['libelle' => 'Adulte', 'valide_du' => ($this->jour)(15), 'age_min' => 18, 'age_max' => null]));

    $trous = $controle->trous($this->course->fresh());

    // Du 11e au 15e jour : personne ; à partir du 16e : les 6-17 ans.
    expect($trous)->toHaveCount(2)
        ->and(ControleTarifs::libelle($trous[0]))->toBe('du '.$this->ouverture->copy()->addDays(10)->format('d/m/Y').' au '.$this->ouverture->copy()->addDays(14)->format('d/m/Y').', 6 ans et plus')
        ->and(ControleTarifs::libelle($trous[1]))->toBe('du '.$this->ouverture->copy()->addDays(15)->format('d/m/Y').' au '.$this->fermeture->format('d/m/Y').', 6 à 17 ans');

    // La page de la course les affiche et préremplit le formulaire avec le premier.
    $this->get("/admin/courses/{$this->course->id_course}")
        ->assertSee('Tarifs à compléter')
        ->assertSee('value="'.$this->ouverture->copy()->addDays(10)->toDateString().'"', false);
});

test('publier exige un tarif pour chaque jour et chaque âge', function () {
    $champs = [
        'nom' => 'Édition test',
        'date_evenement' => $this->evenement->date_evenement->toDateString(),
        'ouverture' => $this->evenement->ouverture->format('Y-m-d\TH:i'),
        'fermeture' => $this->evenement->fermeture->format('Y-m-d\TH:i'),
        'statut' => Evenement::PUBLIE,
    ];
    $this->course->tarifs()->create(($this->tarif)(['libelle' => 'Enfant', 'age_max' => 17, 'age_min' => null]));

    $this->put("/admin/evenements/{$this->evenement->id_evenement}", $champs)->assertSessionHasErrors('statut');
    expect(session('errors')->first('statut'))->toContain('5 km n\'a pas de tarif')->toContain('18 ans et plus');
    expect($this->evenement->fresh()->statut)->toBe(Evenement::BROUILLON);

    $this->course->tarifs()->create(($this->tarif)(['libelle' => 'Adulte', 'age_min' => 18, 'age_max' => null]));
    $this->put("/admin/evenements/{$this->evenement->id_evenement}", $champs)->assertSessionHasNoErrors();
    expect($this->evenement->fresh()->statut)->toBe(Evenement::PUBLIE);
});

test("un événement publié avec des trous est signalé sur le tableau de bord", function () {
    $this->evenement->update(['statut' => Evenement::PUBLIE]);
    $this->course->tarifs()->create(($this->tarif)(['valide_au' => ($this->jour)(19), 'age_min' => null, 'age_max' => null]));

    $this->get('/admin?evenement='.$this->evenement->id_evenement)
        ->assertSee('Inscriptions bloquées')
        ->assertSee('5 km n&#039;a pas de tarif', false);
});

test('publier exige le règlement, la décharge et la RGPD ; le droit à l\'image reste facultatif', function () {
    $this->course->tarifs()->create(($this->tarif)(['age_min' => null, 'age_max' => null]));
    App\Models\TexteLegal::where('id_evenement', $this->evenement->id_evenement)->where('type_texte', 'decharge')->delete();
    $champs = [
        'nom' => 'Édition test',
        'date_evenement' => $this->evenement->date_evenement->toDateString(),
        'ouverture' => $this->evenement->ouverture->format('Y-m-d\TH:i'),
        'fermeture' => $this->evenement->fermeture->format('Y-m-d\TH:i'),
        'statut' => Evenement::PUBLIE,
    ];

    $this->put("/admin/evenements/{$this->evenement->id_evenement}", $champs)->assertSessionHasErrors('statut');
    expect(session('errors')->first('statut'))->toContain('il manque : décharge de responsabilité');

    App\Models\TexteLegal::create(['type_texte' => 'decharge', 'version' => 1, 'date_publication' => today(), 'fichier' => 'textes/decharge.pdf', 'id_evenement' => $this->evenement->id_evenement]);
    $this->put("/admin/evenements/{$this->evenement->id_evenement}", $champs)->assertSessionHasNoErrors();
});

test('la checklist « Prêt à publier ? » liste ce qui manque, seulement en brouillon', function () {
    App\Models\TexteLegal::where('id_evenement', $this->evenement->id_evenement)->where('type_texte', 'rgpd')->delete();

    $this->get("/admin/evenements/{$this->evenement->id_evenement}")
        ->assertSee('Prêt à publier ?')
        ->assertSee('Encore 2 point(s) à régler') // tarifs du 5 km, RGPD
        ->assertSee('Tarifs de « 5 km »')
        ->assertSee('Aucun PDF publié.')
        ->assertSee('(facultatif)');

    $this->evenement->update(['statut' => Evenement::PUBLIE]);
    $this->get("/admin/evenements/{$this->evenement->id_evenement}")->assertDontSee('Prêt à publier ?');
});
