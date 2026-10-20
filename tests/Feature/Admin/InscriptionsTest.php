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

    $lecteur = admin(1);
    $csv = lireCsv($this->actingAs($lecteur)->get('/admin/inscriptions/export?mineur=1')->assertOk()->streamedContent());

    expect($csv)->toStartWith("sep=;\r\nN°;Nom;Prénom;");

    $csv = lireCsv($this->get('/admin/inscriptions/export')->assertOk()->streamedContent());

    expect($csv)->toContain("'=Durand;Camille;") // formule neutralisée
        ->and($csv)->toContain(';"Course 10 km";Validée;1000;25,00;');

    // Chaque export est tracé au journal, avec son auteur et ses filtres.
    $journal = JournalAdmin::where('action', 'export.inscrits')->orderBy('id_journal')->get();
    expect($journal)->toHaveCount(2)
        ->and($journal->first()->id_admin)->toBe($lecteur->id_user)
        ->and($journal->first()->details)->toBe(['mineur' => '1']);
});

test("l'export des dossards liste les inscriptions validées par numéro", function () {
    inscriptionPayee(User::factory()->create(['nom' => 'Second']), $this->dix)->update(['dossard' => 1001]);
    inscriptionPayee(User::factory()->create(['nom' => 'Premier']), $this->dix)->update(['dossard' => 1000]);
    inscriptionPayee(User::factory()->create(['nom' => 'Incomplet']), $this->dix, Inscription::DOSSIER_INCOMPLET);

    $csv = lireCsv($this->actingAs(admin(1))->get('/admin/inscriptions/dossards')->assertOk()->streamedContent());
    $lignes = array_values(array_filter(explode("\r\n", $csv)));

    expect($lignes)->toHaveCount(4)
        ->and($lignes[2])->toStartWith('1000;Premier;')
        ->and($lignes[3])->toStartWith('1001;Second;');
});

test("le CSV s'ouvre colonne par colonne dans Excel : ligne sep=;, Windows-1252, accents conservés", function () {
    inscriptionPayee(User::factory()->create(['prenom' => 'Zoé', 'nom' => 'Lefèvre-Œuvray']), $this->dix)->update(['dossard' => 1000]);

    $brut = $this->actingAs(admin(1))->get('/admin/inscriptions/dossards')->assertOk()->streamedContent();

    expect($brut)->toStartWith("sep=;\r\n")
        ->and(mb_check_encoding($brut, 'UTF-8'))->toBeFalse() // pas de l'UTF-8…
        ->and(str_contains($brut, "\xE9"))->toBeTrue()         // … mais du Windows-1252 (é = 0xE9)
        ->and(lireCsv($brut))->toContain('1000;Lefèvre-Œuvray;Zoé;');
});

test('sans PDF de décharge, les affirmations du tunnel suffisent', function () {
    App\Models\TexteLegal::where('type_texte', 'decharge')->delete();
    $inscription = inscriptionPayee(User::factory()->create(), $this->dix, Inscription::DOSSIER_INCOMPLET);
    expect($inscription->documentsManquants())->toBe(['la décharge de responsabilité']);

    $inscription = documentsComplets($inscription);
    expect($inscription->dechargeManquante())->toBeFalse()
        ->and($inscription->typesConsentis())->not->toContain('decharge')
        ->and(Inscription::avecDocumentsManquants($this->evenement)->pluck('id_inscription')->all())->not->toContain($inscription->id_inscription);

    app(App\Services\Inscriptions\MettreAJourStatut::class)($inscription);
    expect($inscription->fresh()->statut)->toBe(Inscription::VALIDEE);
});

/** Champs du formulaire « Modifier les informations », d'après le participant de l'inscription. */
function champsParticipant(Inscription $i, array $d = []): array
{
    $p = $i->participant;

    return [
        'prenom' => $p->user->prenom,
        'nom' => $p->user->nom,
        'date_naissance' => $p->date_naissance->toDateString(),
        'sexe' => (string) $p->sexe,
        'telephone' => (string) $p->user->telephone,
        'email_contact' => (string) $p->email_contact,
        'adresse' => '',
        'code_postal' => '',
        'ville' => '',
        'pays' => 'France',
        'urgence_nom' => '',
        'urgence_tel' => '',
        ...$d,
    ];
}

test('un super admin corrige les informations du participant, et la correction est tracée', function () {
    $parent = User::factory()->create(['nom' => 'Bernard']);
    $enfant = enfantInscrit($parent, $this->cinq);

    $this->actingAs(admin(3))->get("/admin/inscriptions/{$enfant->id_inscription}")->assertSee('Modifier les informations');

    $this->put("/admin/inscriptions/{$enfant->id_inscription}/participant", champsParticipant($enfant, [
        'prenom' => 'Léon',
        'telephone' => '06 12 34 56 78',
        'email_contact' => 'Leon@Exemple.fr',
        'urgence_nom' => 'Mamie',
        'urgence_tel' => '0698765432',
    ]))->assertSessionHasNoErrors();

    $enfant = $enfant->fresh();
    expect($enfant->participant->user->prenom)->toBe('Léon')
        ->and($enfant->participant->user->telephone)->toBe('0612345678')
        ->and($enfant->participant->email_contact)->toBe('leon@exemple.fr')
        ->and($enfant->urgence_nom)->toBe('Mamie');

    expect(JournalAdmin::where('action', 'inscription.participant')->sole()->details)->toMatchArray(['prenom' => 'Léo → Léon']);
});

test("une date de naissance incompatible avec la course, ou qui change le statut mineur, est refusée", function () {
    $adulte = inscriptionPayee(User::factory()->create(), $this->dix); // né en 1980, 10 km dès 16 ans

    $this->actingAs(admin(3));
    // 12 ans le jour J : hors de la course 10 km.
    $this->put("/admin/inscriptions/{$adulte->id_inscription}/participant", champsParticipant($adulte, ['date_naissance' => $this->evenement->date_evenement->copy()->subYears(12)->toDateString()]))
        ->assertSessionHasErrorsIn('participant', 'date_naissance');
    // 17 ans : accepté par la course, mais deviendrait mineur.
    $this->put("/admin/inscriptions/{$adulte->id_inscription}/participant", champsParticipant($adulte, ['date_naissance' => $this->evenement->date_evenement->copy()->subYears(17)->toDateString()]))
        ->assertSessionHasErrorsIn('participant', 'date_naissance');
    // Correction d'un jour, toujours majeur : acceptée.
    $this->put("/admin/inscriptions/{$adulte->id_inscription}/participant", champsParticipant($adulte, ['date_naissance' => '1980-01-02']))
        ->assertSessionHasNoErrors();

    expect($adulte->participant->fresh()->date_naissance->toDateString())->toBe('1980-01-02');
});

test("seul le super admin modifie les informations du participant", function () {
    $inscription = inscriptionPayee(User::factory()->create(), $this->dix);

    $this->actingAs(admin(2))->get("/admin/inscriptions/{$inscription->id_inscription}")->assertDontSee('Modifier les informations');
    $this->put("/admin/inscriptions/{$inscription->id_inscription}/participant", champsParticipant($inscription, ['prenom' => 'Pirate']))->assertForbidden();

    expect($inscription->participant->user->fresh()->prenom)->not->toBe('Pirate');
});
