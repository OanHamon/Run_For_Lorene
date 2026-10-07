<?php

use App\Models\Commande;
use App\Models\Course;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\Participant;
use App\Models\User;
use App\Services\Paiement\EnregistrerPaiement;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;

beforeEach(function () {
    Notification::fake();
    $this->seed();
    config(['paiement.passerelle' => 'simulation']);

    $this->parent = User::factory()->create(['prenom' => 'Sam', 'nom' => 'Leroy']);
    $this->actingAs($this->parent);
    $this->marche = Course::where('nom', 'Marche 5 km')->first();
});

/** Passe le tunnel pour chaque [choix, prénom, naissance], puis paie si demandé. */
function panier(array $personnes, Course $course, bool $commander = true)
{
    $tunnel = Volt::test('inscription.tunnel');

    foreach ($personnes as [$choix, $prenom, $naissance]) {
        $tunnel->call('ajouterParticipant')
            ->call('choisirParticipant', $choix)
            ->set('prenom', $prenom)
            ->set('dateNaissance', $naissance)
            ->call('participantSuivant')
            ->call('choisirCourse', $course->id_course)
            ->call('epreuveSuivant')
            ->remplirDocuments();
    }

    $tunnel->call('choisirDon', 10)->call('donSuivant');

    if ($commander) {
        $tunnel->call('payer')->assertHasNoErrors();
    }

    return Inscription::orderBy('id_inscription')->get();
}

test('chaque inscription du tableau de bord mène à sa page de suivi', function () {
    [$inscription] = panier([['moi', 'Sam', '1985-06-01']], $this->marche);

    $this->get('/espace')->assertOk()->assertSee(route('espace.inscription', $inscription));
    $this->get(route('espace.inscription', $inscription))->assertOk()
        ->assertSee('Informations personnelles')
        ->assertSee('Décharge et consentements')
        ->assertSee('Questionnaire de santé')
        ->assertSee('Paiement')
        ->assertSee('Supprimer l\'inscription', false);
});

test("on ne voit pas l'inscription d'un autre compte", function () {
    [$inscription] = panier([['moi', 'Sam', '1985-06-01']], $this->marche);

    $this->actingAs(User::factory()->create())
        ->get(route('espace.inscription', $inscription))
        ->assertNotFound();
});

test('la timeline reflète l\'avancement', function () {
    [$inscription] = panier([['moi', 'Sam', '1985-06-01']], $this->marche);

    $page = Volt::test('espace.inscription', ['inscription' => $inscription]);
    expect($page->instance()->etapes)->toBe([
        'infos' => 'fait', 'decharge' => 'fait', 'sante' => 'non_requis', 'paiement' => 'en_cours',
    ]);

    app(EnregistrerPaiement::class)->reussi(Paiement::sole()->reference, 22.0);

    $page = Volt::test('espace.inscription', ['inscription' => $inscription->fresh()]);
    expect($page->instance()->etapes['paiement'])->toBe('fait');
    $page->assertSeeText('Dossard n° 3000')->assertDontSee('Supprimer l\'inscription', false);
});

test("le responsable modifie les informations de l'enfant, contact d'urgence compris", function () {
    [, $enfant] = panier([['moi', 'Sam', '1985-06-01'], ['nouveau', 'Lou', '2018-02-10']], $this->marche);

    Volt::test('espace.inscription', ['inscription' => $enfant])
        ->call('modifier')
        ->set('prenom', 'Louise')
        ->set('sexe', 'femme')
        ->set('codePostal', '44 000')
        ->set('ville', 'Nantes')
        ->set('urgenceNom', 'Alex Leroy')
        ->set('urgenceTel', '+33 6 12 34 56 78')
        ->call('enregistrer')
        ->assertHasNoErrors()
        ->assertSet('edition', false)
        ->assertSee('Louise');

    $enfant->refresh();
    expect($enfant->participant->user->prenom)->toBe('Louise')
        ->and($enfant->participant->sexe)->toBe('femme')
        ->and($enfant->participant->code_postal)->toBe('44000')
        ->and($enfant->urgence_nom)->toBe('Alex Leroy')
        ->and($enfant->urgence_tel)->toBe('0612345678');
});

test('formats invalides et date de naissance incompatible avec l\'épreuve refusés', function () {
    $this->parent->participant()->create(['date_naissance' => '1985-06-01']);
    $dix = Course::where('nom', 'Course 10 km')->first();
    [$inscription] = panier([['moi', 'Sam', '1985-06-01']], $dix);

    Volt::test('espace.inscription', ['inscription' => $inscription])
        ->call('modifier')
        ->set('telephone', '1234')
        ->set('codePostal', '4400')
        ->set('urgenceNom', 'Alex')
        ->call('enregistrer')
        ->assertHasErrors(['telephone', 'codePostal', 'urgenceTel'])
        ->set('telephone', '')->set('codePostal', '')->set('urgenceNom', '')
        ->set('dateNaissance', '2015-01-01') // 12 ans le jour J, le 10 km est dès 16 ans
        ->call('enregistrer')
        ->assertHasErrors('dateNaissance');

    expect($inscription->participant->fresh()->date_naissance->format('Y-m-d'))->toBe('1985-06-01');
});

test("le droit à l'image se modifie et chaque réponse est historisée", function () {
    [$inscription] = panier([['moi', 'Sam', '1985-06-01']], $this->marche);
    $documents = $inscription->documents()->count();

    Volt::test('espace.inscription', ['inscription' => $inscription])
        ->assertSet('droitImage', false)
        ->set('droitImage', true)
        ->set('droitImage', false);

    expect($inscription->documents()->count())->toBe($documents + 2);
});

test('supprimer un brouillon', function () {
    [$inscription] = panier([['moi', 'Sam', '1985-06-01']], $this->marche, commander: false);

    Volt::test('espace.inscription', ['inscription' => $inscription])
        ->call('supprimer')
        ->assertRedirect(route('espace'));

    expect(Inscription::count())->toBe(0);
});

test('supprimer une inscription en attente de paiement : les autres de la commande repassent en brouillon', function () {
    [$moi, $enfant] = panier([['moi', 'Sam', '1985-06-01'], ['nouveau', 'Lou', '2018-02-10']], $this->marche);
    $commande = Commande::sole();

    Volt::test('espace.inscription', ['inscription' => $enfant])
        ->call('supprimer')
        ->assertRedirect(route('espace'));

    expect(Inscription::whereKey($enfant->id_inscription)->exists())->toBeFalse()
        ->and($moi->fresh()->statut)->toBe(Inscription::BROUILLON)
        ->and($commande->fresh()->statut)->toBe(Commande::ABANDONNEE)
        ->and(session('tunnel.don'))->toBe('10')
        // L'enfant reste dans les participants gérés, prêt pour une prochaine inscription.
        ->and(Participant::where('id_responsable', $this->parent->id_user)->count())->toBe(1);

    $this->get('/espace')->assertSee('reste à payer');
});

test('une inscription payée ne peut pas être supprimée', function () {
    [$inscription] = panier([['moi', 'Sam', '1985-06-01']], $this->marche);
    app(EnregistrerPaiement::class)->reussi(Paiement::sole()->reference, 22.0);

    Volt::test('espace.inscription', ['inscription' => $inscription->fresh()])
        ->call('supprimer')
        ->assertForbidden();

    expect($inscription->fresh()->statut)->toBe(Inscription::VALIDEE);
});
