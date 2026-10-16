<?php

use App\Models\Course;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\User;

/*
 * Verrous du paramétrage, déclenchés par la première inscription engagée (payée ou en cours
 * de paiement) : âges et quota minimal de la course, montant / début / âges d'un tarif déjà
 * choisi, date de l'événement, retour en brouillon. Le reste reste libre.
 */

beforeEach(function () {
    $this->seed();
    $this->evenement = Evenement::first();
    $this->course = Course::where('nom', 'Course 5 km')->first();
    $this->tarif = $this->course->tarifs()->first();
    $this->actingAs(admin(3));

    $this->champsCourse = fn (array $d = []) => [
        'nom' => $this->course->nom,
        'heure_depart' => '10:30',
        'distance' => '5',
        'age_min' => (string) $this->course->age_min,
        'age_max' => '',
        'quota' => (string) $this->course->quota,
        'dossard_debut' => (string) $this->course->dossard_debut,
        'dossard_fin' => (string) $this->course->dossard_fin,
        ...$d,
    ];
    $this->champsTarif = fn (array $d = []) => [
        'libelle' => $this->tarif->libelle,
        'montant' => (string) $this->tarif->montant,
        'valide_du' => $this->tarif->valide_du->toDateString(),
        'valide_au' => $this->tarif->valide_au->toDateString(),
        'age_min' => (string) $this->tarif->age_min,
        'age_max' => '',
        ...$d,
    ];
    $this->champsEvenement = fn (array $d = []) => [
        'nom' => $this->evenement->nom,
        'date_evenement' => $this->evenement->date_evenement->toDateString(),
        'ouverture' => $this->evenement->ouverture->format('Y-m-d\TH:i'),
        'fermeture' => $this->evenement->fermeture->format('Y-m-d\TH:i'),
        'statut' => $this->evenement->statut,
        ...$d,
    ];
});

test('sans inscrit, tout reste modifiable', function () {
    // Un brouillon ne compte pas comme inscrit.
    inscriptionPayee(User::factory()->create(), $this->course, Inscription::BROUILLON);

    $this->put("/admin/courses/{$this->course->id_course}", ($this->champsCourse)(['age_min' => '14', 'quota' => '1']))->assertSessionHasNoErrors();
    $this->put("/admin/tarifs/{$this->tarif->id_tarif}", ($this->champsTarif)(['montant' => '19', 'age_min' => '14']))->assertSessionHasNoErrors();
    $this->put("/admin/evenements/{$this->evenement->id_evenement}", ($this->champsEvenement)(['statut' => 'brouillon']))->assertSessionHasNoErrors();
});

test('avec des inscrits, les âges de la course sont figés et le quota a un plancher', function () {
    inscriptionPayee(User::factory()->create(), $this->course);
    inscriptionPayee(User::factory()->create(), $this->course, Inscription::EN_ATTENTE_PAIEMENT);

    $this->put("/admin/courses/{$this->course->id_course}", ($this->champsCourse)(['age_min' => '14']))->assertSessionHasErrors('age_min');
    $this->put("/admin/courses/{$this->course->id_course}", ($this->champsCourse)(['quota' => '1']))
        ->assertSessionHasErrors(['quota' => 'La course a déjà 2 inscrit(s) : le quota ne peut pas descendre en dessous.']);

    // Toujours libre : nom, heure, quota en hausse ou au plancher, places illimitées.
    $this->put("/admin/courses/{$this->course->id_course}", ($this->champsCourse)(['nom' => '5 km nature', 'heure_depart' => '10:45', 'quota' => '2']))->assertSessionHasNoErrors();
    $this->put("/admin/courses/{$this->course->id_course}", ($this->champsCourse)(['nom' => '5 km nature', 'quota' => '', 'dossard_fin' => '2999']))->assertSessionHasNoErrors();
    expect($this->course->fresh())->nom->toBe('5 km nature')->quota->toBeNull();
});

test("un tarif déjà choisi garde son montant, son début et ses âges, mais peut finir plus tôt ou plus tard", function () {
    inscriptionPayee(User::factory()->create(), $this->course);
    $url = "/admin/tarifs/{$this->tarif->id_tarif}";

    $this->put($url, ($this->champsTarif)(['montant' => '20']))->assertSessionHasErrors('montant');
    $this->put($url, ($this->champsTarif)(['valide_du' => $this->evenement->ouverture->copy()->addDay()->toDateString()]))->assertSessionHasErrors('valide_du');
    $this->put($url, ($this->champsTarif)(['age_min' => '14']))->assertSessionHasErrors('age_min');
    $this->put($url, ($this->champsTarif)(['valide_au' => today()->subDay()->toDateString()]))->assertSessionHasErrors('valide_au');

    // Terminer le tarif aujourd'hui, puis en créer un nouveau dès demain au nouveau prix.
    $this->put($url, ($this->champsTarif)(['valide_au' => today()->toDateString(), 'libelle' => 'Tarif de lancement']))->assertSessionHasNoErrors();
    $this->post("/admin/courses/{$this->course->id_course}/tarifs", ($this->champsTarif)(['libelle' => 'Plein tarif', 'montant' => '22', 'valide_du' => today()->addDay()->toDateString(), 'valide_au' => $this->evenement->fermeture->toDateString()]))
        ->assertSessionHasNoErrors();

    expect($this->tarif->fresh()->montant)->toBe('18.00')
        ->and($this->course->tarifs()->count())->toBe(2);
});

test("avec des inscrits, l'événement garde sa date et ne repasse pas en brouillon", function () {
    inscriptionPayee(User::factory()->create(), $this->course);
    $url = "/admin/evenements/{$this->evenement->id_evenement}";

    $this->put($url, ($this->champsEvenement)(['date_evenement' => $this->evenement->date_evenement->copy()->addDay()->toDateString(), 'fermeture' => $this->evenement->fermeture->format('Y-m-d\TH:i')]))
        ->assertSessionHasErrors('date_evenement');
    $this->put($url, ($this->champsEvenement)(['statut' => 'brouillon']))->assertSessionHasErrors('statut');

    // Toujours possible : renommer, prolonger les inscriptions, clôturer.
    $this->put($url, ($this->champsEvenement)(['nom' => 'RFL3', 'fermeture' => $this->evenement->fermeture->copy()->addDays(2)->format('Y-m-d\TH:i')]))->assertSessionHasNoErrors();
    $this->put($url, ($this->champsEvenement)(['nom' => 'RFL3', 'statut' => 'cloture']))->assertSessionHasNoErrors();
    expect($this->evenement->fresh()->statut)->toBe(Evenement::CLOTURE);
});

test('les champs verrouillés sont signalés dans les formulaires', function () {
    inscriptionPayee(User::factory()->create(), $this->course);

    $this->get("/admin/courses/{$this->course->id_course}")->assertSee('Figé : la course a des inscrits.');
    $this->get("/admin/tarifs/{$this->tarif->id_tarif}")->assertSee('Des inscrits ont déjà choisi ce tarif');
    $this->get("/admin/evenements/{$this->evenement->id_evenement}")->assertSee('Figée : l&#039;événement a des inscrits.', false);
});
