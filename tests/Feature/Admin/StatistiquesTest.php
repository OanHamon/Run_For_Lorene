<?php

use App\Models\Course;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\Participant;
use App\Models\User;
use App\Services\Admin\Graphiques;

beforeEach(function () {
    $this->seed();
    $this->evenement = Evenement::first();
    $this->course = Course::where('id_evenement', $this->evenement->id_evenement)->whereNotNull('quota')->first();
    $this->graphiques = app(Graphiques::class);
});

/** Inscription réglée dont la commande date de $date, pour un participant né le $naissance. */
function inscritLe(Course $course, string $date, array $participant = []): Inscription
{
    $user = User::factory()->create();
    Participant::create(['id_user' => $user->id_user] + $participant + ['date_naissance' => '1990-05-01']);
    $inscription = inscriptionPayee($user, $course);
    $inscription->commande->update(['date_commande' => $date]);

    return $inscription;
}

test('le cumul compte les inscriptions réglées jour par jour, avec l\'ouverture en jalon', function () {
    $this->evenement->update(['ouverture' => now()->subDays(5)->startOfDay(), 'fermeture' => now()->addMonth()]);
    inscritLe($this->course, now()->subDays(4)->setTime(10, 0));
    inscritLe($this->course, now()->subDays(4)->setTime(18, 0));
    inscritLe($this->course, now()->subDay()->setTime(9, 0));
    inscriptionPayee(User::factory()->create(), $this->course, Inscription::EN_ATTENTE_PAIEMENT)->commande->update(['date_commande' => now()->subDay()]);

    $cumul = $this->graphiques->cumul($this->evenement->fresh());

    expect($cumul['jours'])->toHaveCount(6)
        ->and($cumul['parJour'])->toBe([0, 2, 0, 0, 1, 0])
        ->and($cumul['cumul'])->toBe([0, 2, 2, 2, 3, 3])
        ->and($cumul['jalons'][0])->toBe(['jour' => now()->subDays(5)->toDateString(), 'libelle' => 'Ouverture']);
});

test('la projection donne la date à laquelle la course serait complète', function () {
    $this->evenement->update(['fermeture' => now()->addYear()]);
    $this->course->update(['quota' => 10]);
    foreach (range(1, 7) as $n) {
        inscritLe($this->course, now()->subDays($n));
    }

    $r = collect($this->graphiques->remplissage($this->evenement->fresh()))->firstWhere('course', $this->course->nom);

    // 7 inscriptions en 14 jours : 0,5 par jour ; 3 places restantes → dans 6 jours.
    expect($r['places'])->toBe(7)
        ->and($r['rythme'])->toBe(0.5)
        ->and($r['complete_le'])->toBe(today()->addDays(6)->translatedFormat('j F Y'));
});

test("l'entonnoir va des inscriptions commencées aux validées", function () {
    inscritLe($this->course, now());
    inscriptionPayee(User::factory()->create(), $this->course, Inscription::DOSSIER_INCOMPLET);
    inscriptionPayee(User::factory()->create(), $this->course, Inscription::BROUILLON);

    expect(array_column($this->graphiques->entonnoir($this->evenement), 'nombre', 'etape'))
        ->toBe(['Commencées' => 3, 'Paiement lancé' => 2, 'Réglées' => 2, 'Validées' => 1]);
});

test('âge, sexe et département des inscrits', function () {
    $date = $this->evenement->date_evenement;
    inscritLe($this->course, now(), ['sexe' => 'femme', 'date_naissance' => $date->copy()->subYears(35)->toDateString(), 'code_postal' => '44000']);
    inscritLe($this->course, now(), ['sexe' => 'homme', 'date_naissance' => $date->copy()->subYears(10)->toDateString(), 'code_postal' => '44300']);
    inscritLe($this->course, now(), ['sexe' => null, 'date_naissance' => $date->copy()->subYears(15)->toDateString(), 'code_postal' => '97400']);
    inscritLe($this->course, now(), ['pays' => 'Belgique', 'code_postal' => '1000']);

    $repartition = $this->graphiques->repartition($this->evenement);
    expect($repartition['ages']['30 à 39 ans']['Femmes'])->toBe(1)
        ->and($repartition['ages']['Moins de 12 ans']['Hommes'])->toBe(1)
        ->and($repartition['ages']['12 à 17 ans']['Non renseigné'])->toBe(1);

    expect($this->graphiques->departements($this->evenement))->toBe(['44' => 2, '974' => 1, 'Étranger' => 1]);
});

test('les moments comptent les inscriptions par jour de semaine et tranche de deux heures', function () {
    inscritLe($this->course, '2026-11-02 21:15:00'); // lundi
    $moments = $this->graphiques->moments($this->evenement);

    expect($moments['valeurs'][0][10])->toBe(1)->and($moments['max'])->toBe(1);
});

test('la page Statistiques s\'affiche, avec ses graphiques et leurs valeurs', function () {
    inscritLe($this->course, now());

    $this->actingAs(admin(1))->get('/admin/statistiques')->assertOk()
        ->assertSee('Inscriptions jour par jour')
        ->assertSee('Remplissage des courses')
        ->assertSee('Recettes par semaine')
        ->assertSee('data-graphique', false)
        ->assertSee('admin-graphiques', false)
        ->assertSee('Voir les valeurs');
});

test('sans inscription, les graphiques l\'indiquent', function () {
    $this->actingAs(admin(1))->get('/admin/statistiques')->assertOk()->assertSee('Pas encore de données.');
});
