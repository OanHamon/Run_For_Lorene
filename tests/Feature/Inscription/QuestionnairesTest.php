<?php

use App\Models\Course;
use App\Models\Document;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\QuestionSante;
use App\Models\ReponseSante;
use App\Models\User;
use App\Services\Inscriptions\Consentements;
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

/** Tunnel arrivé à l'étape Documents pour « moi », adulte, sur la marche. */
function auxDocuments()
{
    return Volt::test('inscription.tunnel')
        ->call('choisirParticipant', 'moi')
        ->set('dateNaissance', '1985-06-01')
        ->call('participantSuivant')
        ->call('choisirCourse', test()->marche->id_course)
        ->call('epreuveSuivant')
        ->assertSet('etape', 'documents')
        ->assertSet('volet', 'decharge');
}

/** Tunnel arrivé au questionnaire de santé de Noé, 15 ans le jour de l'événement (3 avril 2027). */
function auxSante()
{
    return Volt::test('inscription.tunnel')
        ->call('choisirParticipant', 'nouveau')
        ->set('prenom', 'Noé')
        ->set('dateNaissance', '2011-06-01')
        ->call('participantSuivant')
        ->call('choisirCourse', test()->marche->id_course)
        ->call('epreuveSuivant')
        ->assertSet('participantMineur', true)
        ->set(documentsRemplis())
        ->call('dechargeSuivant')
        ->assertHasNoErrors()
        ->assertSet('volet', 'sante');
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
});

test('un adulte remplit la décharge puis les conditions, sans questionnaire de santé', function () {
    $decharge = app(Questionnaires::class)->questions(QuestionSante::DECHARGE);

    auxDocuments()
        ->assertSet('volets', ['decharge', 'conditions'])
        ->assertSee('Tes documents')
        ->assertSee('1 sur 2')
        ->assertSee('Décharge de responsabilité')
        ->assertSee('samedi 3 avril 2027')
        ->assertSee('Je certifie que :')
        ->assertSee($decharge->first()->libelle)
        ->assertSee('Je coche les affirmations suivantes')
        ->assertSee('Leroy Sam')
        ->assertSee(today()->translatedFormat('j F Y'))
        ->assertDontSee('Questionnaire de santé')
        ->assertDontSee('traitement des données personnelles')
        ->assertDontSee('Plus tard')
        ->set(documentsRemplis())
        ->call('dechargeSuivant')
        ->assertHasNoErrors()
        ->assertSet('volet', 'conditions')
        ->assertSee('2 sur 2')
        ->assertSee('traitement des données personnelles')
        ->call('conditionsSuivant')
        ->assertSet('etape', 'don');

    $inscription = Inscription::sole();
    expect($inscription->santeRequise())->toBeFalse()
        ->and($inscription->documentsManquants())->toBe([])
        ->and(app(Questionnaires::class)->dernier($inscription, QuestionSante::SANTE))->toBeNull();
});

test('un mineur a le questionnaire de santé entre la décharge et les conditions, famille remplie par ses parents', function () {
    $sante = app(Questionnaires::class)->questions(QuestionSante::SANTE);

    auxSante()
        ->assertSet('volets', ['decharge', 'sante', 'conditions'])
        ->assertSee('Les documents de Noé')
        ->assertSee('2 sur 3')
        ->assertSee('Noé sera mineur(e) le jour de la course')
        ->assertSee('Partie suivante')
        ->assertSee("Depuis l'année dernière")
        ->assertSee($sante->first()->libelle)
        ->assertSee($sante->last()->libelle)
        ->assertSee('Questions à remplir par les parents de Noé')
        ->assertDontSee('Ta famille et ton suivi médical')
        ->assertSee('Noé doit consulter un médecin')
        ->assertSee("J'ai lu et j'approuve l'ensemble des réponses données pour Noé à ce questionnaire.")
        ->call('retour')
        ->assertSet('volet', 'decharge')
        ->call('dechargeSuivant')
        ->call('santeSuivant')
        ->assertHasNoErrors()
        ->assertSet('volet', 'conditions')
        ->assertSee('3 sur 3');
});

test('la décharge incomplète bloque la suite, rien n\'est enregistré', function () {
    $decharge = affirmations();
    $oubliee = array_key_last($decharge);
    $decharge[$oubliee] = false;

    auxDocuments()
        ->set(documentsRemplis())
        ->set('reponsesDecharge', $decharge)
        ->call('dechargeSuivant')
        ->assertHasErrors(["reponsesDecharge.{$oubliee}"])
        ->assertSet('volet', 'decharge')
        ->assertSee('Coche cette affirmation pour continuer.')
        ->assertSee('Il manque des cases');

    expect(Document::count())->toBe(0)
        ->and(Inscription::sole()->consentementsComplets())->toBeFalse();
});

test('la décharge exige l\'approbation finale', function () {
    auxDocuments()
        ->set(documentsRemplis())
        ->set('accepteDecharge', false)
        ->call('dechargeSuivant')
        ->assertHasErrors(['accepteDecharge'])
        ->assertSet('volet', 'decharge');
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

    auxSante()
        ->set('reponsesSante', $sante)
        ->call('santeSuivant')
        ->assertHasErrors(["reponsesSante.{$premiere}"])
        ->assertSet('volet', 'sante');
});

test('le questionnaire de santé exige l\'attestation finale', function () {
    auxSante()
        ->set('attestationSante', false)
        ->call('santeSuivant')
        ->assertHasErrors(['attestationSante'])
        ->assertSet('volet', 'sante');
});

test('le questionnaire de santé d\'un mineur ne peut pas être sauté', function () {
    // Conditions envoyées sans passer par la santé : retour au questionnaire, pas au don.
    auxSante()->call('conditionsSuivant')->assertSet('etape', 'documents')->assertSet('volet', 'sante');

    // Nouvelle visite : reprise sur le questionnaire de santé, et le panier signale le manque.
    $tunnel = Volt::test('inscription.tunnel')->assertSet('etape', 'documents')->assertSet('volet', 'sante');
    expect($tunnel->instance()->alertesPanier)->toBe([Inscription::sole()->id_inscription => 'Documents à compléter.']);

    expect(Inscription::sole()->documentsManquants())->toBe(['le questionnaire de santé']);
});

test('les réponses sont chiffrées en base et liées à la question posée, pour ce coureur', function () {
    auxSante()->call('santeSuivant')->call('conditionsSuivant')->assertSet('etape', 'don');

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
    $tunnel = auxSante()->call('santeSuivant')->call('conditionsSuivant')->assertSet('etape', 'don');
    $inscription = Inscription::sole();

    // Retour sans rien changer : réponses pré-remplies, pas de nouveau document.
    $tunnel->call('modifier', $inscription->id_inscription, 'documents')
        ->assertSet('volet', 'decharge')
        ->assertSet('reponsesDecharge', affirmations())
        ->assertSet('reponsesSante', reponses('sante'))
        ->assertSet('attestationSante', true)
        ->call('dechargeSuivant')
        ->call('santeSuivant')
        ->call('conditionsSuivant');

    expect(Document::has('questionnaireSante')->count())->toBe(2);

    // Une réponse change : nouveau questionnaire, l'ancien est supprimé logiquement.
    $sante = reponses('sante');
    $sante[array_key_first($sante)] = 'oui';
    $tunnel->call('modifier', $inscription->id_inscription, 'documents')
        ->call('dechargeSuivant')
        ->set('reponsesSante', $sante)
        ->call('santeSuivant');

    expect(Document::has('questionnaireSante')->count())->toBe(2)
        ->and(Document::onlyTrashed()->count())->toBe(1)
        ->and(app(Questionnaires::class)->reponses($inscription, QuestionSante::SANTE))->toBe($sante);
});

test('un adulte payé est validé avec dossard, sans questionnaire de santé', function () {
    auxDocuments()->remplirDocuments()->call('donPasser')->call('payer')->assertHasNoErrors();

    app(EnregistrerPaiement::class)->reussi(Paiement::sole()->reference, (float) Paiement::sole()->montant);

    $inscription = Inscription::sole();
    expect($inscription->statut)->toBe(Inscription::VALIDEE)
        ->and($inscription->dossard)->not->toBeNull();

    Volt::test('espace.inscription', ['inscription' => $inscription])
        ->assertSee("Le questionnaire de santé n'est demandé qu'aux participants mineurs.", false)
        ->assertDontSee('Envoyer le questionnaire')
        ->assertSet('etapes.sante', 'non_requis');
});

test('un mineur payé avec tous ses documents est validé avec dossard', function () {
    auxSante()->call('santeSuivant')->call('conditionsSuivant')->call('donPasser')->call('payer')->assertHasNoErrors();

    app(EnregistrerPaiement::class)->reussi(Paiement::sole()->reference, (float) Paiement::sole()->montant);

    $inscription = Inscription::sole();
    expect($inscription->statut)->toBe(Inscription::VALIDEE)
        ->and($inscription->dossard)->not->toBeNull();
});

test('dossier incomplet (décharge manquante) : la décharge se remplit depuis l\'espace et valide l\'inscription', function () {
    $inscription = inscriptionPayee($this->user, $this->marche, Inscription::DOSSIER_INCOMPLET);
    foreach (['reglement', 'rgpd'] as $type) {
        app(Consentements::class)->accepter($inscription, $type, $this->user->id_user, '127.0.0.1');
    }

    expect($inscription->documentsManquants())->toBe(['la décharge de responsabilité']);

    $page = Volt::test('espace.inscription', ['inscription' => $inscription])
        ->assertSee('Envoyer la décharge')
        ->assertDontSee('Envoyer le questionnaire');

    $page->call('enregistrerDecharge')->assertHasErrors(['accepteDecharge', 'reponsesDecharge.'.array_key_first(affirmations())]);
    $page->set('reponsesDecharge', affirmations())
        ->set('accepteDecharge', true)
        ->call('enregistrerDecharge')
        ->assertHasNoErrors()
        ->assertDontSee('Envoyer la décharge');

    $inscription->refresh();
    expect($inscription->statut)->toBe(Inscription::VALIDEE)
        ->and($inscription->dossard)->not->toBeNull()
        ->and($page->instance()->etapes)->toMatchArray(['decharge' => 'fait', 'sante' => 'non_requis']);
});

test('une décharge déjà remplie ne se renvoie pas depuis l\'espace', function () {
    auxDocuments()->remplirDocuments();

    Volt::test('espace.inscription', ['inscription' => Inscription::sole()])
        ->assertDontSee('Envoyer la décharge')
        ->set('reponsesDecharge', affirmations())
        ->set('accepteDecharge', true)
        ->call('enregistrerDecharge')
        ->assertForbidden();
});

test('un questionnaire de santé déjà rempli ne se renvoie pas depuis l\'espace', function () {
    auxSante()->call('santeSuivant')->call('conditionsSuivant');

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

    auxSante()->assertSee('Question de la version 2 ?')
        ->set(documentsRemplis())
        ->call('santeSuivant')
        ->assertHasNoErrors();

    expect(ReponseSante::whereHas('question', fn ($q) => $q->where('type_questionnaire', 'sante'))->sole()->id_question)
        ->toBe($nouvelle->id_question);
});
