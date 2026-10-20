<?php

use App\Models\Commande;
use App\Models\Course;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\CommandeConfirmee;
use App\Services\Paiement\EnregistrerPaiement;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;

beforeEach(function () {
    Notification::fake();
    $this->seed();
    config(['paiement.passerelle' => 'simulation']);

    $this->parent = User::factory()->create(['prenom' => 'Sam', 'nom' => 'Leroy']);
    $this->course = fn (string $nom) => Course::where('nom', $nom)->first();
});

/** Fait passer un participant par Participant → Épreuve → Documents. */
function inscrire($tunnel, string $choix, string $prenom, string $naissance, int $courseId)
{
    $tunnel->call('choisirParticipant', $choix)
        ->set('prenom', $prenom)
        ->set('dateNaissance', $naissance)
        ->call('participantSuivant')
        ->assertHasNoErrors()
        ->assertSet('etape', 'epreuve')
        ->call('choisirCourse', $courseId)
        ->call('epreuveSuivant')
        ->assertHasNoErrors()
        ->assertSet('etape', 'documents')
        ->remplirDocuments()
        ->assertHasNoErrors();

    return $tunnel;
}

test('un invité voit l\'identification dans le tunnel', function () {
    $this->get('/inscription')->assertOk()->assertSee('Connecte-toi');
});

test('un parent inscrit un enfant et lui-même, paie, et chacun reçoit un dossard', function () {
    $this->actingAs($this->parent);

    $tunnel = Volt::test('inscription.tunnel')->assertSet('etape', 'participant');

    inscrire($tunnel, 'moi', 'Sam', '1985-06-01', ($this->course)('Course 10 km')->id_course)
        ->assertSet('etape', 'don')
        ->call('choisirDon', 10)
        ->call('donSuivant')
        ->assertSet('etape', 'recap');

    $tunnel->call('ajouterParticipant')->assertSet('etape', 'participant');
    inscrire($tunnel, 'nouveau', 'Lou', '2018-02-10', ($this->course)('Course enfants')->id_course)
        ->assertSet('etape', 'recap')
        ->assertSee('Lou Leroy')
        ->assertSee('Sam Leroy');

    $enfant = Participant::where('id_responsable', $this->parent->id_user)->first();
    expect($enfant->user->email)->toBeNull()
        ->and($enfant->user->mot_de_passe)->toBeNull();

    // 25 € + 5 € + 10 € de don
    $tunnel->assertSee('40 €')->call('payer')->assertHasNoErrors();

    $commande = Commande::first();
    expect((float) $commande->montant_total)->toBe(40.0)
        ->and($commande->statut)->toBe(Commande::EN_COURS)
        ->and((float) $commande->don->montant)->toBe(10.0)
        ->and($commande->inscriptions)->toHaveCount(2)
        ->and($commande->inscriptions->pluck('statut')->unique()->all())->toBe([Inscription::EN_ATTENTE_PAIEMENT]);

    $paiement = $commande->paiements()->sole();
    $tunnel->assertRedirect(route('paiement.simulation', $paiement));

    app(EnregistrerPaiement::class)->reussi($paiement->reference, 40.0, 0.93);

    $commande->refresh();
    expect($commande->statut)->toBe(Commande::PAYEE)
        ->and($paiement->fresh()->statut)->toBe(Paiement::REUSSI);

    $adulte = $commande->inscriptions->firstWhere('id_participant', $this->parent->id_user);
    $petit = $commande->inscriptions->firstWhere('id_participant', $enfant->id_user);
    expect($adulte->statut)->toBe(Inscription::VALIDEE)
        ->and($adulte->dossard)->toBe(1000)
        ->and((float) $adulte->prix_paye)->toBe(25.0)
        ->and($petit->statut)->toBe(Inscription::VALIDEE)
        ->and($petit->dossard)->toBe(4000);

    Notification::assertSentTo($this->parent, CommandeConfirmee::class);

    $this->get(route('inscription.confirmation', $commande))->assertOk()->assertSee('Dossard n° 1000');
    $this->get('/espace')->assertOk()->assertSee('Lou')->assertSee('Validée');
});

test('téléphone et email facultatifs : enregistrés pour un enfant, téléphone seul pour soi', function () {
    $this->actingAs($this->parent);
    $this->parent->update(['email' => 'sam@example.org']);
    $tunnel = Volt::test('inscription.tunnel');

    // Facultatifs : on passe sans les remplir.
    $tunnel->call('choisirParticipant', 'moi')
        ->assertSee('Téléphone')
        ->assertDontSee('id="email"', false)
        ->assertSee('Tes emails arrivent sur sam@example.org')
        ->set('dateNaissance', '1985-06-01')
        ->set('telephone', '+33 6 12 34 56 78')
        ->call('participantSuivant')
        ->assertHasNoErrors()
        ->assertSet('etape', 'epreuve');

    expect($this->parent->fresh()->telephone)->toBe('0612345678');

    // Enfant : formats vérifiés, puis enregistrés sur son profil (pas d'email de connexion).
    $tunnel->call('ajouterParticipant')
        ->call('choisirParticipant', 'nouveau')
        ->set('prenom', 'Lou')
        ->set('dateNaissance', '2015-01-01')
        ->set('telephone', '12')
        ->set('email', 'pas-un-email')
        ->call('participantSuivant')
        ->assertHasErrors(['telephone', 'email'])
        ->set('telephone', '07 11 22 33 44')
        ->set('email', ' Lou@Example.org ')
        ->call('participantSuivant')
        ->assertHasNoErrors()
        ->assertSet('etape', 'epreuve');

    $lou = Participant::where('id_responsable', $this->parent->id_user)->sole();
    expect($lou->email_contact)->toBe('lou@example.org')
        ->and($lou->user->telephone)->toBe('0711223344')
        ->and($lou->user->email)->toBeNull();

    // Reprise du participant géré : champs pré-remplis.
    $tunnel->call('ajouterParticipant')
        ->call('choisirParticipant', (string) $lou->id_user)
        ->assertSet('telephone', '07 11 22 33 44')
        ->assertSet('email', 'lou@example.org');
});

test("seules les épreuves ouvertes à l'âge le jour J sont proposées", function () {
    $this->actingAs($this->parent);

    $tunnel = Volt::test('inscription.tunnel')
        ->call('choisirParticipant', 'nouveau')
        ->set('prenom', 'Lou')
        ->set('dateNaissance', '2018-02-10')
        ->call('participantSuivant');

    $noms = $tunnel->instance()->courses->pluck('nom')->all();
    expect($noms)->toContain('Course enfants', 'Marche 5 km')->not->toContain('Course 10 km');

    // Forcer une épreuve non éligible est refusé côté serveur.
    $tunnel->call('choisirCourse', ($this->course)('Course 10 km')->id_course)
        ->call('epreuveSuivant')
        ->assertHasErrors('courseId')
        ->assertSet('etape', 'epreuve');
});

test("l'âge est calculé à la date de l'événement et non à la date d'inscription", function () {
    $this->actingAs($this->parent);

    // 15 ans aujourd'hui, 16 ans le 3 avril 2027 : le 10 km (dès 16 ans) est ouvert.
    Volt::test('inscription.tunnel')
        ->call('choisirParticipant', 'nouveau')
        ->set('prenom', 'Noé')
        ->set('dateNaissance', '2011-03-01')
        ->call('participantSuivant')
        ->call('choisirCourse', ($this->course)('Course 10 km')->id_course)
        ->call('epreuveSuivant')
        ->assertHasNoErrors()
        ->assertSet('etape', 'documents');
});

test('les consentements obligatoires bloquent la suite, le droit à l\'image est facultatif et enregistré', function () {
    $this->actingAs($this->parent);

    $tunnel = Volt::test('inscription.tunnel')
        ->call('choisirParticipant', 'moi')
        ->set('dateNaissance', '1985-06-01')
        ->call('participantSuivant')
        ->call('choisirCourse', ($this->course)('Marche 5 km')->id_course)
        ->call('epreuveSuivant')
        ->set(documentsRemplis())
        ->call('dechargeSuivant')
        ->assertSet('volet', 'conditions')
        ->set(['accepteReglement' => true, 'accepteRgpd' => false])
        ->call('conditionsSuivant')
        ->assertHasErrors(['accepteRgpd'])
        ->assertSet('volet', 'conditions');

    $tunnel->set(['accepteRgpd' => true, 'accepteImage' => false])->call('conditionsSuivant')->assertSet('etape', 'don');

    $inscription = Inscription::sole();
    expect($inscription->consentementsComplets())->toBeTrue()
        ->and($inscription->documents()->has('consentement')->count())->toBe(4) // 3 obligatoires + droit à l'image (refusé)
        ->and($inscription->documents()->has('consentement')->with('consentement')->get()->pluck('consentement.ip')->unique()->all())->toBe(['127.0.0.1']);
});

test('une épreuve complète ne peut pas être choisie', function () {
    $this->actingAs($this->parent);
    $course = ($this->course)('Course 5 km');
    $course->update(['quota' => 1]);
    inscriptionPayee(User::factory()->create(), $course);

    Volt::test('inscription.tunnel')
        ->call('choisirParticipant', 'moi')
        ->set('dateNaissance', '1985-06-01')
        ->call('participantSuivant')
        ->call('choisirCourse', $course->id_course)
        ->call('epreuveSuivant')
        ->assertHasErrors('courseId');
});

test('un participant déjà inscrit ne peut pas être réinscrit (unicité par événement)', function () {
    $this->actingAs($this->parent);
    inscriptionPayee($this->parent, ($this->course)('Marche 5 km'));

    Volt::test('inscription.tunnel')
        ->call('choisirParticipant', 'moi')
        ->set('dateNaissance', '1985-06-01')
        ->call('participantSuivant')
        ->assertHasErrors('choix')
        ->assertSet('etape', 'participant');
});

test("un participant déjà inscrit ne change ni de nom ni de date de naissance par le tunnel", function () {
    $this->actingAs($this->parent);
    $inscription = inscriptionPayee($this->parent, ($this->course)('Marche 5 km'));
    $naissance = $inscription->participant->date_naissance->toDateString();

    Volt::test('inscription.tunnel')
        ->call('choisirParticipant', 'moi')
        ->set('prenom', 'Autre')
        ->set('dateNaissance', '2012-01-01') // deviendrait mineur : questionnaire de santé contourné
        ->call('participantSuivant')
        ->assertHasErrors('choix');

    expect($inscription->participant->fresh()->date_naissance->toDateString())->toBe($naissance)
        ->and($this->parent->fresh()->prenom)->toBe('Sam');
});

test('le brouillon est repris là où on s\'était arrêté', function () {
    $this->actingAs($this->parent);

    Volt::test('inscription.tunnel')
        ->call('choisirParticipant', 'moi')
        ->set('dateNaissance', '1985-06-01')
        ->call('participantSuivant')
        ->call('choisirCourse', ($this->course)('Marche 5 km')->id_course)
        ->call('epreuveSuivant')
        ->assertSet('etape', 'documents');

    // Nouvelle visite : on revient aux documents du même participant, épreuve conservée.
    Volt::test('inscription.tunnel')
        ->assertSet('etape', 'documents')
        ->assertSet('volet', 'decharge')
        ->assertSet('participantId', $this->parent->id_user)
        ->assertSet('courseId', ($this->course)('Marche 5 km')->id_course);
});

test('retirer une personne du panier', function () {
    $this->actingAs($this->parent);

    $tunnel = Volt::test('inscription.tunnel');
    inscrire($tunnel, 'moi', 'Sam', '1985-06-01', ($this->course)('Marche 5 km')->id_course)->call('donPasser');

    $id = Inscription::sole()->id_inscription;
    $tunnel->call('retirer', $id)->assertSet('etape', 'participant');

    expect(Inscription::count())->toBe(0);
});

test('inscriptions fermées', function () {
    Evenement::query()->update(['fermeture' => now()->subDay()]);
    $this->actingAs($this->parent);

    Volt::test('inscription.tunnel')->assertSet('etape', 'ferme')->assertSee('Inscriptions fermées');
});

test('un paiement en attente est proposé à la place du tunnel', function () {
    $this->actingAs($this->parent);

    $tunnel = Volt::test('inscription.tunnel');
    inscrire($tunnel, 'moi', 'Sam', '1985-06-01', ($this->course)('Marche 5 km')->id_course)
        ->call('donPasser')
        ->call('payer');

    Volt::test('inscription.tunnel')->assertSet('etape', 'attente')->assertSee('Un paiement t\'attend', false);
});

test('une commande à 0 € est validée sans passer par le paiement', function () {
    $this->actingAs($this->parent);
    ($this->course)('Marche 5 km')->tarifs()->update(['montant' => 0]);

    $tunnel = Volt::test('inscription.tunnel');
    inscrire($tunnel, 'moi', 'Sam', '1985-06-01', ($this->course)('Marche 5 km')->id_course)
        ->call('donPasser')
        ->call('payer');

    $commande = Commande::sole();
    $tunnel->assertRedirect(route('inscription.confirmation', $commande));
    expect($commande->statut)->toBe(Commande::PAYEE)
        ->and($commande->paiements()->count())->toBe(0)
        ->and(Inscription::sole()->statut)->toBe(Inscription::VALIDEE);
});

test('le don accepte la virgule et refuse les montants farfelus', function () {
    $this->actingAs($this->parent);

    $tunnel = Volt::test('inscription.tunnel');
    inscrire($tunnel, 'moi', 'Sam', '1985-06-01', ($this->course)('Marche 5 km')->id_course)
        ->set('don', '-5')->call('donSuivant')->assertHasErrors('don')
        ->set('don', '12,345')->call('donSuivant')->assertHasErrors('don')
        ->set('don', '12,50')->call('donSuivant')->assertHasNoErrors()->assertSet('etape', 'recap')
        ->assertSee('12,50 €');
});

test("on ne peut pas modifier le participant géré d'un autre compte", function () {
    $autre = User::factory()->create();
    $enfant = User::create(['prenom' => 'Zoé', 'nom' => 'X']);
    Participant::create(['id_user' => $enfant->id_user, 'date_naissance' => '2015-01-01', 'id_responsable' => $autre->id_user]);

    $this->actingAs($this->parent);

    Volt::test('inscription.tunnel')
        ->call('choisirParticipant', (string) $enfant->id_user)
        ->assertSet('choix', '');
});
