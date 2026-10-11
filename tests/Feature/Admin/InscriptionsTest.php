<?php

use App\Models\Commande;
use App\Models\Course;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\JournalAdmin;
use App\Models\Paiement;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\RelanceInscription;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed();
    $this->evenement = Evenement::first();
    $this->dix = Course::where('nom', 'Course 10 km')->first();
    $this->cinq = Course::where('nom', 'Course 5 km')->first();
});

/** Enfant géré par $parent, inscrit et payé sur $course. */
function enfantInscrit(User $parent, Course $course, string $statut = Inscription::DOSSIER_INCOMPLET): Inscription
{
    $enfant = User::create(['prenom' => 'Léo', 'nom' => $parent->nom]);
    Participant::create(['id_user' => $enfant->id_user, 'date_naissance' => '2013-06-01', 'id_responsable' => $parent->id_user]);

    $inscription = inscriptionPayee($enfant, $course, $statut);
    $inscription->commande->update(['id_user' => $parent->id_user]);

    return $inscription->fresh();
}

test('la liste filtre par course, statut, mineur et documents manquants, et cherche par nom', function () {
    $adulte = User::factory()->create(['prenom' => 'Camille', 'nom' => 'Durand']);
    $complete = documentsComplets(inscriptionPayee($adulte, $this->dix));
    $parent = User::factory()->create(['nom' => 'Bernard']);
    $enfant = enfantInscrit($parent, $this->cinq);
    $brouillon = inscriptionPayee(User::factory()->create(['nom' => 'Zorglub']), $this->cinq, Inscription::BROUILLON);

    $this->actingAs(admin(1));

    $this->get('/admin/inscriptions')->assertOk()
        ->assertSee('Camille Durand')->assertSee('Léo Bernard')->assertDontSee('Zorglub');

    $this->get('/admin/inscriptions?course='.$this->cinq->id_course)->assertDontSee('Camille Durand')->assertSee('Léo Bernard');
    $this->get('/admin/inscriptions?statut=validee')->assertSee('Camille Durand')->assertDontSee('Léo Bernard');
    $this->get('/admin/inscriptions?mineur=1')->assertDontSee('Camille Durand')->assertSee('Léo Bernard');
    $this->get('/admin/inscriptions?documents=1')->assertDontSee('Camille Durand')->assertSee('Léo Bernard');
    $this->get('/admin/inscriptions?q=durand')->assertSee('Camille Durand')->assertDontSee('Léo Bernard');
    $this->get('/admin/inscriptions?q='.$parent->email)->assertSee('Léo Bernard')->assertDontSee('Camille Durand');
});

test('le filtre « documents manquants » suit la règle du dossier complet', function () {
    $complet = documentsComplets(inscriptionPayee(User::factory()->create(), $this->dix));
    $sansDecharge = inscriptionPayee(User::factory()->create(), $this->dix, Inscription::DOSSIER_INCOMPLET);
    $enfant = enfantInscrit(User::factory()->create(), $this->cinq);
    app(\App\Services\Inscriptions\Questionnaires::class)->remplirDecharge($enfant, affirmations(), $enfant->participant->id_responsable, '127.0.0.1');

    $ids = Inscription::avecDocumentsManquants($this->evenement)->pluck('id_inscription')->sort()->values()->all();

    expect($ids)->toBe([$sansDecharge->id_inscription, $enfant->id_inscription])
        ->and($complet->documentsManquants())->toBe([])
        ->and($enfant->fresh()->documentsManquants())->toBe(['le questionnaire de santé']);
});

test('la fiche détaillée réunit participant, course, paiement et documents', function () {
    $parent = User::factory()->create(['nom' => 'Bernard']);
    $enfant = enfantInscrit($parent, $this->cinq);

    $this->actingAs(admin(1))->get("/admin/inscriptions/{$enfant->id_inscription}")
        ->assertOk()
        ->assertSee('Léo Bernard')
        ->assertSee($parent->email)
        ->assertSee('Course 5 km')
        ->assertSee('Dossier incomplet')
        ->assertSee('la décharge de responsabilité et le questionnaire de santé')
        ->assertDontSee('Annuler l\'inscription'); // lecture seule
});

test("un admin modifie un dossard : plage de la course et unicité par événement vérifiées", function () {
    $a = inscriptionPayee(User::factory()->create(), $this->dix);
    $b = inscriptionPayee(User::factory()->create(), $this->dix);
    $a->update(['dossard' => 1000]);
    $b->update(['dossard' => 1001]);

    $this->actingAs($admin = admin(2));

    $this->put("/admin/inscriptions/{$b->id_inscription}/dossard", ['dossard' => 1000])->assertSessionHasErrors('dossard');
    $this->put("/admin/inscriptions/{$b->id_inscription}/dossard", ['dossard' => 2500])->assertSessionHasErrors('dossard');
    $this->put("/admin/inscriptions/{$b->id_inscription}/dossard", ['dossard' => 1500])->assertSessionHasNoErrors();

    expect($b->fresh()->dossard)->toBe(1500);

    $trace = JournalAdmin::where('action', 'inscription.dossard')->sole();
    expect($trace->id_admin)->toBe($admin->id_user)
        ->and($trace->cible_id)->toBe($b->id_inscription)
        ->and($trace->details)->toBe(['avant' => 1001, 'après' => 1500]);
});

test('une inscription validée restée sans dossard en reçoit un à la demande', function () {
    $sans = inscriptionPayee(User::factory()->create(), $this->dix);
    inscriptionPayee(User::factory()->create(), $this->dix)->update(['dossard' => 1000]);

    $this->actingAs(admin(2))->post("/admin/inscriptions/{$sans->id_inscription}/dossard")->assertSessionHas('statut');

    expect($sans->fresh()->dossard)->toBe(1001);
});

test("annuler une inscription validée libère son dossard", function () {
    $inscription = inscriptionPayee(User::factory()->create(), $this->dix);
    $inscription->update(['dossard' => 1000]);

    $this->actingAs(admin(2))->post("/admin/inscriptions/{$inscription->id_inscription}/annuler")->assertSessionHas('statut');

    expect($inscription->fresh())
        ->statut->toBe(Inscription::ANNULEE)
        ->dossard->toBeNull();
    expect(JournalAdmin::where('action', 'inscription.annulation')->sole()->details)->toMatchArray(['dossard libéré' => 1000]);
});

test("annuler une inscription en attente de paiement ferme la commande et remet les autres en brouillon", function () {
    $parent = User::factory()->create();
    $a = inscriptionPayee($parent, $this->dix, Inscription::EN_ATTENTE_PAIEMENT);
    $commande = $a->commande;
    $commande->update(['statut' => Commande::EN_COURS]);
    $enfant = User::create(['prenom' => 'Léo', 'nom' => 'X']);
    Participant::create(['id_user' => $enfant->id_user, 'date_naissance' => '2013-06-01', 'id_responsable' => $parent->id_user]);
    $b = Inscription::create(['id_participant' => $enfant->id_user, 'id_tarif' => $this->cinq->tarifs()->first()->id_tarif, 'id_evenement' => $this->evenement->id_evenement, 'id_commande' => $commande->id_commande, 'statut' => Inscription::EN_ATTENTE_PAIEMENT, 'prix_paye' => 18]);
    Paiement::create(['reference' => 'sim_test', 'montant' => 28, 'statut' => Paiement::EN_ATTENTE, 'id_commande' => $commande->id_commande, 'prestataire' => 'simulation']);

    $this->actingAs(admin(2))->post("/admin/inscriptions/{$a->id_inscription}/annuler")->assertSessionHas('statut');

    expect($a->fresh()->statut)->toBe(Inscription::ANNULEE)
        ->and($b->fresh()->statut)->toBe(Inscription::BROUILLON)
        ->and($b->fresh()->id_commande)->toBeNull()
        ->and($commande->fresh()->statut)->toBe(Commande::ABANDONNEE)
        ->and(Paiement::where('reference', 'sim_test')->value('statut'))->toBe(Paiement::ECHOUE);
});

test('relancer un dossier incomplet envoie un email au responsable, une fois par heure', function () {
    $parent = User::factory()->create();
    $enfant = enfantInscrit($parent, $this->cinq);

    $this->actingAs(admin(2));
    $this->post("/admin/inscriptions/{$enfant->id_inscription}/relancer")->assertSessionHas('statut', "Relance envoyée à {$parent->email}.");
    $this->post("/admin/inscriptions/{$enfant->id_inscription}/relancer")->assertSessionHas('erreur');

    Notification::assertSentToTimes($parent, RelanceInscription::class, 1);
    Notification::assertSentTo($parent, RelanceInscription::class, function ($n) use ($parent) {
        $mail = $n->toMail($parent);

        return $mail->subject === 'Il manque un document pour prendre le départ'
            && str_contains(implode(' ', $mail->introLines), 'le questionnaire de santé');
    });
    expect(JournalAdmin::where('action', 'inscription.relance')->count())->toBe(1);
});

test('les actions sur une inscription demandent le niveau 2', function () {
    $inscription = inscriptionPayee(User::factory()->create(), $this->dix);

    $this->actingAs(admin(1));
    $this->put("/admin/inscriptions/{$inscription->id_inscription}/dossard", ['dossard' => 1500])->assertForbidden();
    $this->post("/admin/inscriptions/{$inscription->id_inscription}/annuler")->assertForbidden();

    expect($inscription->fresh()->statut)->toBe(Inscription::VALIDEE);
});

test("l'export CSV des inscrits est lisible par un tableur", function () {
    $adulte = User::factory()->create(['prenom' => 'Camille', 'nom' => '=Durand']);
    inscriptionPayee($adulte, $this->dix)->update(['dossard' => 1000, 'prix_paye' => 25]);

    $csv = $this->actingAs(admin(1))->get('/admin/inscriptions/export')->assertOk()->streamedContent();

    expect($csv)->toStartWith("\xEF\xBB\xBFN°;Nom;Prénom;")
        ->and($csv)->toContain("'=Durand;Camille;") // formule neutralisée
        ->and($csv)->toContain(';"Course 10 km";Validée;1000;25,00;');
});

test("l'export des dossards liste les inscriptions validées par numéro", function () {
    inscriptionPayee(User::factory()->create(['nom' => 'Second']), $this->dix)->update(['dossard' => 1001]);
    inscriptionPayee(User::factory()->create(['nom' => 'Premier']), $this->dix)->update(['dossard' => 1000]);
    inscriptionPayee(User::factory()->create(['nom' => 'Incomplet']), $this->dix, Inscription::DOSSIER_INCOMPLET);

    $csv = $this->actingAs(admin(1))->get('/admin/inscriptions/dossards')->assertOk()->streamedContent();
    $lignes = array_values(array_filter(explode("\n", $csv)));

    expect($lignes)->toHaveCount(3)
        ->and($lignes[1])->toStartWith('1000;Premier;')
        ->and($lignes[2])->toStartWith('1001;Second;');
});
