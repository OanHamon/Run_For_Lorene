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

test('les questions provisoires sont en base : 10 de santé et 10 de décharge', function () {
    $questionnaires = app(Questionnaires::class);

    expect($questionnaires->questions(QuestionSante::SANTE))->toHaveCount(10)
        ->and($questionnaires->questions(QuestionSante::DECHARGE))->toHaveCount(10);

    auxDocuments()
        ->assertSee('Décharge de responsabilité')
        ->assertSee('Questionnaire de santé')
        ->assertSee(QuestionSante::where('type_questionnaire', 'sante')->first()->libelle);
});

test('la décharge incomplète bloque la suite, rien n\'est enregistré', function () {
    $decharge = reponses('decharge', 'oui');
    $oubliee = array_key_last($decharge);
    unset($decharge[$oubliee]);

    auxDocuments()
        ->set(documentsRemplis())
        ->set('reponsesDecharge', $decharge)
        ->call('documentsSuivant')
        ->assertHasErrors(["reponsesDecharge.{$oubliee}"])
        ->assertSet('etape', 'documents')
        ->assertSee('Il manque des réponses');

    expect(Document::count())->toBe(0)
        ->and(Inscription::sole()->consentementsComplets())->toBeFalse();
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
        ->and($sante->reponses)->toHaveCount(10)
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
        ->assertSet('reponsesDecharge', reponses('decharge', 'oui'))
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

test('santé remplie plus tard : dossier incomplet après paiement, puis validé avec dossard depuis l\'espace', function () {
    auxDocuments()
        ->set(documentsRemplis())
        ->set('reponsesSante', [])
        ->set('attestationSante', false)
        ->set('santePlusTard', true)
        ->assertSee('tu pourras le remplir depuis ton espace')
        ->call('documentsSuivant')
        ->assertHasNoErrors()
        ->assertSet('etape', 'don')
        ->call('donPasser')
        ->assertSee('questionnaire de santé à remplir depuis ton espace')
        ->call('payer')
        ->assertHasNoErrors();

    app(EnregistrerPaiement::class)->reussi(Paiement::sole()->reference, (float) Paiement::sole()->montant);

    $inscription = Inscription::sole();
    expect($inscription->statut)->toBe(Inscription::DOSSIER_INCOMPLET)
        ->and($inscription->dossard)->toBeNull()
        ->and($inscription->questionnaireManquant())->toBeTrue();

    $page = Volt::test('espace.inscription', ['inscription' => $inscription])
        ->assertSee('Envoyer le questionnaire')
        ->call('enregistrerSante')
        ->assertHasErrors(['attestationSante']);

    $page->set('reponsesSante', reponses('sante'))
        ->set('attestationSante', true)
        ->call('enregistrerSante')
        ->assertHasNoErrors()
        ->assertSee('l\'inscription est validée')
        ->assertDontSee('Envoyer le questionnaire');

    $inscription->refresh();
    expect($inscription->statut)->toBe(Inscription::VALIDEE)
        ->and($inscription->dossard)->toBe(3000)
        ->and($page->instance()->etapes['sante'])->toBe('fait');
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

    auxDocuments()->assertSee('Question de la version 2 ?')
        ->set(documentsRemplis())
        ->call('documentsSuivant')
        ->assertHasNoErrors();

    expect(ReponseSante::whereHas('question', fn ($q) => $q->where('type_questionnaire', 'sante'))->sole()->id_question)
        ->toBe($nouvelle->id_question);
});
