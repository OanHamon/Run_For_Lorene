<?php

use App\Models\Course;
use App\Models\Document;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\QuestionSante;
use App\Models\ReponseSante;
use App\Models\User;
use App\Services\Inscriptions\Questionnaires;
use App\Services\Paiement\EnregistrerPaiement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;

beforeEach(function () {
    Notification::fake();
    $this->seed();
    config(['paiement.passerelle' => 'simulation']);

    $this->user = User::factory()->create(['prenom' => 'Sam', 'nom' => 'Leroy']);
    $this->actingAs($this->user);
    $this->marche = Course::where('nom', 'Marche 5 km')->first();
});

/** Tunnel arrivé à l'étape Documents pour « moi » sur la marche. */
function auxDocuments()
{
    return Volt::test('inscription.tunnel')
        ->call('choisirParticipant', 'moi')
        ->set('dateNaissance', '1985-06-01')
        ->call('participantSuivant')
        ->call('choisirCourse', test()->marche->id_course)
        ->call('epreuveSuivant')
        ->assertSet('etape', 'documents');
}

test('les questions des Google Forms sont en base : 24 de santé par rubrique, 6 affirmations de décharge', function () {
    $questionnaires = app(Questionnaires::class);
    $sante = $questionnaires->questions(QuestionSante::SANTE);
    $decharge = $questionnaires->questions(QuestionSante::DECHARGE);

    expect($sante)->toHaveCount(24)
        ->and($sante->countBy('rubrique')->all())->toBe(['annee' => 12, 'semaines' => 6, 'aujourdhui' => 3, 'parents' => 3])
        ->and($sante->first()->libelle)->toBe("Es-tu allé(e) à l'hôpital pendant toute une journée ou plusieurs jours ?")
        ->and($decharge)->toHaveCount(6)
        ->and($decharge->last()->libelle)->toBe("J'envoie le présent formulaire volontairement et sans contrainte.");

    auxDocuments()
        ->assertDontSee($sante->first()->libelle)
        ->set('questionnaires', 'maintenant')
        ->assertSee('Décharge de responsabilité')
        ->assertSee('samedi 3 avril 2027')
        ->assertSee('Je certifie que :')
        ->assertSee($decharge->first()->libelle)
        ->assertSee('Je coche les affirmations suivantes')
        ->assertSee('Leroy Sam')
        ->assertSee(today()->translatedFormat('j F Y'))
        ->assertSee("J'ai lu et j'approuve l'ensemble de mes réponses à ce questionnaire.", false)
        ->assertSee('Questionnaire de santé')
        ->assertSee('Partie suivante')
        ->assertSee("Depuis l'année dernière")
        ->assertSee($sante->first()->libelle)
        ->assertSee($sante->last()->libelle)
        ->assertSee('Ta famille et ton suivi médical')
        ->assertDontSee('Questions à faire remplir par tes parents')
        ->assertSee('tu dois consulter un médecin')
        ->assertSee("J'ai lu et j'approuve l'ensemble de mes réponses à ce questionnaire.");
});

test('pour un mineur, les questions de la famille sont à remplir par ses parents', function () {
    // 15 ans le jour de l'événement (3 avril 2027).
    Volt::test('inscription.tunnel')
        ->call('choisirParticipant', 'nouveau')
        ->set('prenom', 'Noé')
        ->set('dateNaissance', '2011-06-01')
        ->call('participantSuivant')
        ->call('choisirCourse', test()->marche->id_course)
        ->call('epreuveSuivant')
        ->assertSet('etape', 'documents')
        ->assertSet('participantMineur', true)
        ->set('questionnaires', 'maintenant')
        ->assertSee('Questions à remplir par les parents de Noé')
        ->assertDontSee('Ta famille et ton suivi médical')
        ->assertSee('Noé doit consulter un médecin');
});

test('la décharge incomplète bloque la suite, rien n\'est enregistré', function () {
    $decharge = affirmations();
    $oubliee = array_key_last($decharge);
    $decharge[$oubliee] = false;

    auxDocuments()
        ->set(documentsRemplis())
        ->set('reponsesDecharge', $decharge)
        ->call('documentsSuivant')
        ->assertHasErrors(["reponsesDecharge.{$oubliee}"])
        ->assertSet('etape', 'documents')
        ->assertSee('Coche cette affirmation pour continuer.')
        ->assertSee('Il manque des réponses');

    expect(Document::count())->toBe(0)
        ->and(Inscription::sole()->consentementsComplets())->toBeFalse();
});

test('la décharge exige l\'approbation finale', function () {
    auxDocuments()
        ->set(documentsRemplis())
        ->set('accepteDecharge', false)
        ->call('documentsSuivant')
        ->assertHasErrors(['accepteDecharge'])
        ->assertSet('etape', 'documents');
});

test('le service refuse une affirmation de décharge non cochée', function () {
    $inscription = auxDocuments()->instance()->panier->first();
    $decharge = affirmations();
    $decharge[array_key_first($decharge)] = 'non';

    expect(fn () => app(Questionnaires::class)->enregistrer($inscription, QuestionSante::DECHARGE, $decharge, true, $this->user->id_user))
        ->toThrow(ValidationException::class);
    expect(Document::count())->toBe(0);
});

test('seuls oui et non sont acceptés', function () {
    $sante = reponses('sante');
    $premiere = array_key_first($sante);
    $sante[$premiere] = 'peut-être';

    auxDocuments()
        ->set(documentsRemplis())
        ->set('reponsesSante', $sante)
        ->call('documentsSuivant')
        ->assertHasErrors(["reponsesSante.{$premiere}"]);
});

test('le questionnaire de santé exige l\'attestation finale', function () {
    auxDocuments()
        ->set(documentsRemplis())
        ->set('attestationSante', false)
        ->call('documentsSuivant')
        ->assertHasErrors(['attestationSante'])
        ->assertSet('etape', 'documents');
});

test('les réponses sont chiffrées en base et liées à la question posée, pour ce coureur', function () {
    auxDocuments()->set(documentsRemplis())->call('documentsSuivant')->assertSet('etape', 'don');

    $inscription = Inscription::sole();
    $questionnaires = app(Questionnaires::class);
    $sante = $questionnaires->dernier($inscription, QuestionSante::SANTE);

    expect($sante->attestation)->toBeTrue()
        ->and($sante->document->id_inscription)->toBe($inscription->id_inscription)
        ->and($sante->document->id_deposant)->toBe($this->user->id_user)
        ->and($sante->reponses)->toHaveCount(24)
        ->and($questionnaires->reponses($inscription, QuestionSante::DECHARGE))->toBe(reponses('decharge', 'oui'))
        ->and($questionnaires->reponses($inscription, QuestionSante::SANTE))->toBe(reponses('sante'));

    $brut = DB::table('reponse_santes')->where('id_document', $sante->id_document)->pluck('reponse');
    expect($brut)->each->not->toBeIn(['oui', 'non']);

    $reponse = ReponseSante::where('id_document', $sante->id_document)->first();
    expect($reponse->question->type_questionnaire)->toBe('sante')
        ->and($reponse->question->version_questionnaire)->toBe(1);
});

test('revenir aux documents reprend les réponses ; les modifier archive l\'ancien questionnaire', function () {
    $tunnel = auxDocuments()->set(documentsRemplis())->call('documentsSuivant')->assertSet('etape', 'don');
    $inscription = Inscription::sole();

    // Retour sans rien changer : réponses pré-remplies, pas de nouveau document.
    $tunnel->call('modifier', $inscription->id_inscription, 'documents')
        ->assertSet('reponsesDecharge', affirmations())
        ->assertSet('reponsesSante', reponses('sante'))
        ->assertSet('attestationSante', true)
        ->call('documentsSuivant');

    expect(Document::has('questionnaireSante')->count())->toBe(2);

    // Une réponse change : nouveau questionnaire, l'ancien est supprimé logiquement.
    $sante = reponses('sante');
    $sante[array_key_first($sante)] = 'oui';
    $tunnel->call('modifier', $inscription->id_inscription, 'documents')
        ->set('reponsesSante', $sante)
        ->call('documentsSuivant');

    expect(Document::has('questionnaireSante')->count())->toBe(2)
        ->and(Document::onlyTrashed()->count())->toBe(1)
        ->and(app(Questionnaires::class)->reponses($inscription, QuestionSante::SANTE))->toBe($sante);
});

test('il faut choisir de remplir les questionnaires maintenant ou plus tard', function () {
    auxDocuments()
        ->set(documentsRemplis())
        ->set('questionnaires', '')
        ->call('documentsSuivant')
        ->assertHasErrors(['questionnaires'])
        ->assertSet('etape', 'documents');
});

test('décharge et santé remplies plus tard : payé en dossier incomplet, puis validé avec dossard depuis l\'espace', function () {
    auxDocuments()
        ->set(['accepteReglement' => true, 'accepteRgpd' => true, 'questionnaires' => 'plus_tard'])
        ->assertSee('tu ne pourras pas prendre le départ')
        ->assertDontSee('Envoyer la décharge')
        ->call('documentsSuivant')
        ->assertHasNoErrors()
        ->assertSet('etape', 'don')
        ->call('donPasser')
        ->assertSee('Pas de départ sans la décharge de responsabilité et le questionnaire de santé')
        ->call('payer')
        ->assertHasNoErrors();

    app(EnregistrerPaiement::class)->reussi(Paiement::sole()->reference, (float) Paiement::sole()->montant);

    $inscription = Inscription::sole();
    expect($inscription->statut)->toBe(Inscription::DOSSIER_INCOMPLET)
        ->and($inscription->dossard)->toBeNull()
        ->and($inscription->documentsManquants())->toBe(['la décharge de responsabilité', 'le questionnaire de santé']);

    $this->get('/espace')->assertSee('pas de départ sans décharge ni questionnaire de santé');

    $page = Volt::test('espace.inscription', ['inscription' => $inscription])
        ->assertSee('Tu ne pourras pas prendre le départ sans la décharge de responsabilité et le questionnaire de santé')
        ->assertSee('Envoyer la décharge')
        ->assertSee('Envoyer le questionnaire');

    // Décharge d'abord : les affirmations et l'approbation sont exigées, puis il reste la santé.
    $page->call('enregistrerDecharge')->assertHasErrors(['accepteDecharge', 'reponsesDecharge.'.array_key_first(affirmations())]);
    $page->set('reponsesDecharge', affirmations())
        ->set('accepteDecharge', true)
        ->call('enregistrerDecharge')
        ->assertHasNoErrors()
        ->assertSee('Il reste le questionnaire de santé à remplir')
        ->assertDontSee('Envoyer la décharge');

    expect($inscription->fresh()->statut)->toBe(Inscription::DOSSIER_INCOMPLET)
        ->and($inscription->fresh()->typesConsentis())->toContain('decharge');

    $page->call('enregistrerSante')->assertHasErrors(['attestationSante']);
    $page->set('reponsesSante', reponses('sante'))
        ->set('attestationSante', true)
        ->call('enregistrerSante')
        ->assertHasNoErrors()
        ->assertSee('l\'inscription est validée')
        ->assertDontSee('Envoyer le questionnaire')
        ->assertDontSee('prendre le départ sans');

    $inscription->refresh();
    expect($inscription->statut)->toBe(Inscription::VALIDEE)
        ->and($inscription->dossard)->toBe(3000)
        ->and($page->instance()->etapes)->toMatchArray(['decharge' => 'fait', 'sante' => 'fait']);
});

test('une décharge déjà remplie ne se renvoie pas depuis l\'espace', function () {
    auxDocuments()->set(documentsRemplis())->call('documentsSuivant');

    Volt::test('espace.inscription', ['inscription' => Inscription::sole()])
        ->assertDontSee('Envoyer la décharge')
        ->set('reponsesDecharge', affirmations())
        ->set('accepteDecharge', true)
        ->call('enregistrerDecharge')
        ->assertForbidden();
});

test('un questionnaire de santé déjà rempli ne se renvoie pas depuis l\'espace', function () {
    auxDocuments()->set(documentsRemplis())->call('documentsSuivant');

    Volt::test('espace.inscription', ['inscription' => Inscription::sole()])
        ->assertDontSee('Envoyer le questionnaire')
        ->set('reponsesSante', reponses('sante', 'oui'))
        ->set('attestationSante', true)
        ->call('enregistrerSante')
        ->assertForbidden();
});

test('une nouvelle version des questions remplace l\'ancienne pour les nouveaux questionnaires', function () {
    QuestionSante::where('type_questionnaire', 'sante')->update(['active' => false]);
    $nouvelle = QuestionSante::create([
        'type_questionnaire' => 'sante',
        'libelle' => 'Question de la version 2 ?',
        'ordre' => 1,
        'version_questionnaire' => 2,
    ]);

    expect(app(Questionnaires::class)->questions('sante')->pluck('id_question')->all())->toBe([$nouvelle->id_question]);

    auxDocuments()->set('questionnaires', 'maintenant')->assertSee('Question de la version 2 ?')
        ->set(documentsRemplis())
        ->call('documentsSuivant')
        ->assertHasNoErrors();

    expect(ReponseSante::whereHas('question', fn ($q) => $q->where('type_questionnaire', 'sante'))->sole()->id_question)
        ->toBe($nouvelle->id_question);
});
