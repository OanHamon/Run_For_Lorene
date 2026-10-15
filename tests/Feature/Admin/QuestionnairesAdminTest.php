<?php

use App\Models\Course;
use App\Models\Document;
use App\Models\Inscription;
use App\Models\JournalAdmin;
use App\Models\Participant;
use App\Models\QuestionSante;
use App\Models\User;
use App\Services\Inscriptions\Questionnaires;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed();
    $this->parent = User::factory()->create(['prenom' => 'Sam', 'nom' => 'Leroy']);
    $enfant = User::create(['prenom' => 'Léo', 'nom' => 'Leroy']);
    Participant::create(['id_user' => $enfant->id_user, 'date_naissance' => '2013-06-01', 'id_responsable' => $this->parent->id_user]);
    $this->inscription = inscriptionPayee($enfant, Course::where('nom', 'Course 5 km')->first(), Inscription::DOSSIER_INCOMPLET);
    $this->inscription->commande->update(['id_user' => $this->parent->id_user]);

    // Un « oui » à la première question de la rubrique « Aujourd'hui ».
    $reponses = reponses('sante');
    $this->question = QuestionSante::where('type_questionnaire', 'sante')->where('rubrique', 'aujourdhui')->orderBy('ordre')->first();
    $reponses[$this->question->id_question] = 'oui';
    $this->questionnaire = app(Questionnaires::class)->enregistrer($this->inscription, 'sante', $reponses, true, $this->parent->id_user);
});

test('les réponses de santé sont réservées au niveau 2', function () {
    $this->actingAs(admin(1));
    $this->get('/admin/questionnaires')->assertForbidden();
    $this->get("/admin/questionnaires/{$this->questionnaire->id_document}")->assertForbidden();
    $this->get('/admin')->assertDontSee('Questionnaires de santé');
    // Sur la fiche, le niveau 1 voit que le questionnaire est rempli, sans lien vers les réponses.
    $this->get("/admin/inscriptions/{$this->inscription->id_inscription}")->assertSee('rempli le')->assertDontSee('Consulter');
});

test('les réponses sont chiffrées en base et déchiffrées à l\'affichage', function () {
    $brut = DB::table('reponse_santes')->where('id_question', $this->question->id_question)->value('reponse');
    expect($brut)->not->toBe('oui');

    $this->actingAs(admin(2))->get("/admin/questionnaires/{$this->questionnaire->id_document}")
        ->assertOk()
        ->assertSee('Léo Leroy')
        ->assertSee($this->question->libelle)
        ->assertSee('1 réponse(s) « oui »')
        ->assertSee($this->parent->email);
});

test('la liste distingue les questionnaires à vérifier, avec un « oui », et vérifiés', function () {
    $this->actingAs(admin(2));

    $this->get('/admin/questionnaires')->assertOk()->assertSee('Léo Leroy');
    $this->get('/admin/questionnaires?filtre=oui')->assertSee('Léo Leroy');
    $this->get('/admin/questionnaires?filtre=verifies')->assertDontSee('Léo Leroy');
});

test('marquer un questionnaire vérifié enregistre qui et quand', function () {
    $this->actingAs($admin = admin(2))
        ->post("/admin/questionnaires/{$this->questionnaire->id_document}/verifier")
        ->assertSessionHas('statut');

    $document = Document::find($this->questionnaire->id_document);
    expect($document->statut)->toBe('verifie')
        ->and($document->id_validateur)->toBe($admin->id_user)
        ->and($document->date_validation)->not->toBeNull();

    expect(JournalAdmin::where('action', 'document.verification')->sole()->cible_id)->toBe($this->inscription->id_inscription);

    $this->get('/admin/questionnaires?filtre=verifies')->assertSee('Léo Leroy');
    $this->get('/admin/questionnaires')->assertDontSee('Léo Leroy');
});

test("vérifier un questionnaire ne change pas le statut de l'inscription", function () {
    $this->actingAs(admin(2))->post("/admin/questionnaires/{$this->questionnaire->id_document}/verifier");

    expect($this->inscription->fresh()->statut)->toBe(Inscription::DOSSIER_INCOMPLET);
});

test('consulter un questionnaire de santé est noté dans le journal', function () {
    $this->actingAs($admin = admin(2))->get("/admin/questionnaires/{$this->questionnaire->id_document}")->assertOk();

    $ligne = JournalAdmin::where('action', 'document.consultation')->sole();
    expect($ligne->id_admin)->toBe($admin->id_user)
        ->and($ligne->cible_type)->toBe('inscription')
        ->and($ligne->cible_id)->toBe($this->inscription->id_inscription)
        ->and($ligne->details)->toBe(['document' => $this->questionnaire->id_document])
        ->and($ligne->libelle())->toBe('Questionnaire de santé consulté');
});

test("une consultation refusée n'est pas notée", function () {
    $this->actingAs(admin(1))->get("/admin/questionnaires/{$this->questionnaire->id_document}")->assertForbidden();

    expect(JournalAdmin::where('action', 'document.consultation')->exists())->toBeFalse();
});
