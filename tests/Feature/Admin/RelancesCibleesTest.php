<?php

use App\Models\Course;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\JournalAdmin;
use App\Models\Reglage;
use App\Models\Relance;
use App\Models\RelanceProgrammee;
use App\Models\User;
use App\Notifications\RelanceAdmin;
use App\Support\ReglagesRelances;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed();
    Notification::fake();
    $this->evenement = Evenement::first();
    $this->evenement->update(['date_evenement' => today()->addMonths(2)]);
    $this->course = Course::where('id_evenement', $this->evenement->id_evenement)->first();
});

/** Champs de l'assistant, complétés par $champs (« À qui ? » : tous les inscrits ; « Quand ? » : tout de suite). */
function relance(array $champs = []): array
{
    return $champs + ['modele' => 'libre', 'sujet' => 'Objet', 'message' => "Bonjour {prenom},\n\nCourse : {course}.", 'qui' => 'tous', 'quand' => 'maintenant', 'confirmation' => '1'];
}

test('les relances sont réservées au niveau 2', function () {
    $this->actingAs(admin(1))->get('/admin/relances')->assertForbidden();
    $this->actingAs(admin(2))->get('/admin/relances')->assertOk()
        ->assertSee('Envoyer un message')->assertSee('Envois programmés')->assertSee('Relances automatiques du site');
});

test("l'assistant se lit en quatre étapes, prérempli par le modèle", function () {
    $page = $this->actingAs(admin(2))->get('/admin/relances/nouvelle?modele=documents')->assertOk()
        ->assertSeeInOrder(['Quel message ?', 'À qui ?', 'Le message', 'Quand ?'])
        ->assertSee('Il manque un document pour prendre le départ')
        ->assertSee('+ Prénom');

    // Le modèle « documents » coche « les personnes à qui il manque un document » et « tout de suite ».
    expect($page->getContent())->toMatch('/id="qui-documents"[^>]*checked/')->toMatch('/id="quand-maintenant"[^>]*checked/');
});

test("l'aperçu dit en clair quand et à combien de personnes, avec un exemple", function () {
    inscriptionPayee(User::factory()->create(['prenom' => 'Camille']), $this->course, Inscription::DOSSIER_INCOMPLET);
    inscriptionPayee(User::factory()->create(), $this->course); // validée : hors cible

    $this->actingAs(admin(2))->post('/admin/relances/apercu', relance(['modele' => 'documents', 'sujet' => 'Il manque {documents}', 'qui' => 'documents']))->assertOk()
        ->assertSee('Ce message partira')->assertSee('tout de suite')
        ->assertSee('Bonjour Camille,')
        ->assertSee('Il manque la décharge de responsabilité')
        ->assertSee('Envoyer maintenant');

    Notification::assertNothingSent();
});

test('« tout de suite » envoie au titulaire de chaque inscription visée, noté au journal', function () {
    inscriptionPayee($a = User::factory()->create(['prenom' => 'Alix']), $this->course, Inscription::DOSSIER_INCOMPLET);
    inscriptionPayee($b = User::factory()->create(), $this->course);

    $this->actingAs($admin = admin(2))->post('/admin/relances', relance(['qui' => 'documents']))->assertRedirect('/admin/relances');

    Notification::assertSentTo($a, RelanceAdmin::class, fn (RelanceAdmin $n) => $n->message === "Bonjour Alix,\n\nCourse : {$this->course->nom}.");
    Notification::assertNotSentTo($b, RelanceAdmin::class);
    expect(Relance::sole()->nb_destinataires)->toBe(1)
        ->and(JournalAdmin::where('action', 'relance.envoi')->where('id_admin', $admin->id_user)->exists())->toBeTrue();
});

test('une seule personne : cherchée par son nom, choisie, et elle seule reçoit le message', function () {
    $noa = inscriptionPayee($u = User::factory()->create(['prenom' => 'Noa', 'nom' => 'Lambert']), $this->course);
    inscriptionPayee($autre = User::factory()->create(), $this->course);

    $this->actingAs(admin(2));
    $this->get(route('admin.inscriptions.show', $noa))->assertSee(route('admin.relances.create', ['inscription' => $noa->id_inscription]));
    $this->get('/admin/relances/nouvelle?qui=personne&chercher=lambert')->assertOk()->assertSee('Noa Lambert');

    // Sans personne choisie : refusé, rien ne part (jamais « tout le monde » par erreur).
    $this->post('/admin/relances', relance(['qui' => 'personne']))->assertSessionHasErrors('inscription');
    Notification::assertNothingSent();

    $this->post('/admin/relances', relance(['qui' => 'personne', 'inscription' => $noa->id_inscription]));
    Notification::assertSentTo($u, RelanceAdmin::class);
    Notification::assertNotSentTo($autre, RelanceAdmin::class);
});

test("une inscription non finalisée n'est jamais visée", function () {
    $attente = inscriptionPayee(User::factory()->create(), $this->course, Inscription::EN_ATTENTE_PAIEMENT);

    $this->actingAs(admin(2));
    $this->post('/admin/relances', relance());
    $this->post('/admin/relances', relance(['qui' => 'personne', 'inscription' => $attente->id_inscription]))->assertSessionHasErrors('inscription');

    Notification::assertNothingSent();
});

test('déjà relancé il y a moins de 48 h : écarté, sauf choix contraire', function () {
    inscriptionPayee($u = User::factory()->create(['prenom' => 'Lou', 'nom' => 'Martel']), $this->course);
    $this->actingAs(admin(2));

    $this->post('/admin/relances', relance());
    $this->post('/admin/relances/apercu', relance())->assertSee('ont déjà reçu un message')->assertSee('Lou Martel');
    $this->post('/admin/relances', relance());
    Notification::assertSentToTimes($u, RelanceAdmin::class, 1);

    $this->post('/admin/relances', relance(['inclure_recents' => '1']));
    Notification::assertSentToTimes($u, RelanceAdmin::class, 2);
});

test('sans la case de confirmation, rien ne part', function () {
    inscriptionPayee(User::factory()->create(), $this->course);

    $this->actingAs(admin(2))->post('/admin/relances', array_diff_key(relance(), ['confirmation' => 1]))->assertSessionHasErrors('confirmation');

    Notification::assertNothingSent();
});

test('un jour précis : programmé, puis envoyé par le planificateur à la date et l\'heure choisies, une fois', function () {
    inscriptionPayee($u = User::factory()->create(), $this->course);
    $jour = today()->addDays(3);

    $this->actingAs(admin(2))->post('/admin/relances', relance(['quand' => 'date', 'date_jour' => $jour->toDateString(), 'heure' => '18:30']))
        ->assertRedirect('/admin/relances')->assertSessionHas('statut', fn ($m) => str_contains($m, 'à 18h30'));
    Notification::assertNothingSent();

    $p = RelanceProgrammee::sole();
    $this->get('/admin/relances')->assertSee('Prochain envoi')->assertSee('18h30');

    $this->travelTo($jour->copy()->setTime(18, 0));
    $this->artisan('rfl:envois-programmes');
    Notification::assertNothingSent();

    $this->travelTo($jour->copy()->setTime(18, 35));
    $this->artisan('rfl:envois-programmes');
    $this->artisan('rfl:envois-programmes');
    Notification::assertSentToTimes($u, RelanceAdmin::class, 1);
    expect(Relance::sole()->id_programmee)->toBe($p->id_programmee)
        ->and($p->fresh()->etat())->toBe('terminee');
});

test("N jours avant l'événement, et régulièrement : la liste est recalculée à chaque envoi", function () {
    $incomplete = inscriptionPayee($u = User::factory()->create(), $this->course, Inscription::DOSSIER_INCOMPLET);
    $this->actingAs(admin(2));

    $du = today()->addDay();
    $this->post('/admin/relances', relance(['qui' => 'documents', 'quand' => 'repetee', 'tous_les' => 7, 'repetee_du' => $du->toDateString(), 'repetee_au' => $du->copy()->addDays(20)->toDateString(), 'heure' => '10:00']));
    $this->post('/admin/relances', relance(['sujet' => 'J-7', 'quand' => 'avant', 'jours_avant' => 7, 'heure' => '09:00']));

    $this->travelTo($du->copy()->setTime(10, 1));
    $this->artisan('rfl:envois-programmes');
    Notification::assertSentToTimes($u, RelanceAdmin::class, 1);

    // Une semaine plus tard : dossier complété entre-temps, il n'est plus visé.
    $incomplete->update(['statut' => Inscription::VALIDEE]);
    $this->travelTo($du->copy()->addDays(7)->setTime(10, 1));
    $this->artisan('rfl:envois-programmes');
    Notification::assertSentToTimes($u, RelanceAdmin::class, 1);

    $this->travelTo($this->evenement->date_evenement->copy()->subDays(7)->setTime(9, 5));
    $this->artisan('rfl:envois-programmes');
    Notification::assertSentTo($u, RelanceAdmin::class, fn ($n) => $n->sujet === 'J-7');
});

test('un envoi programmé se met en pause, se modifie, se supprime', function () {
    $this->actingAs(admin(2))->post('/admin/relances', relance(['quand' => 'date', 'date_jour' => today()->addDays(3)->toDateString(), 'heure' => '10:00']));
    $p = RelanceProgrammee::sole();

    $this->post(route('admin.relances.programmees.pause', $p));
    expect($p->fresh()->actif)->toBeFalse();
    $this->get('/admin/relances')->assertSee('En pause');

    $this->travelTo(today()->addDays(3)->setTime(11, 0));
    $this->artisan('rfl:envois-programmes');
    Notification::assertNothingSent();
    $this->travelBack();

    $this->get(route('admin.relances.programmees.edit', $p))->assertOk()->assertSee('Modifier l&#039;envoi programmé', false);
    $this->post('/admin/relances', relance(['programmee' => $p->id_programmee, 'sujet' => 'Nouvel objet', 'quand' => 'date', 'date_jour' => today()->addDays(4)->toDateString(), 'heure' => '11:00']));
    expect(RelanceProgrammee::sole()->sujet)->toBe('Nouvel objet');

    $this->delete(route('admin.relances.programmees.destroy', $p));
    expect(RelanceProgrammee::count())->toBe(0);
});

test('un moment déjà passé, ou après l\'événement, est refusé', function () {
    $this->actingAs(admin(2));

    $this->post('/admin/relances', relance(['quand' => 'date', 'date_jour' => today()->subDay()->toDateString(), 'heure' => '10:00']))->assertSessionHasErrors('quand');
    $this->post('/admin/relances', relance(['quand' => 'date', 'date_jour' => $this->evenement->date_evenement->copy()->addDay()->toDateString(), 'heure' => '10:00']))->assertSessionHasErrors('quand');
    $this->post('/admin/relances', relance(['quand' => 'avant', 'jours_avant' => 120, 'heure' => '10:00']))->assertSessionHasErrors('quand');

    expect(RelanceProgrammee::count())->toBe(0);
});

test('les relances automatiques du site se règlent depuis la page', function () {
    $this->actingAs(admin(2))->put('/admin/relances/reglages', ['documents_actif' => '1', 'documents_jours' => 21, 'documents_heure' => '18:00', 'paiement_heures' => 48])
        ->assertSessionHas('statut');

    expect(ReglagesRelances::documents())->toBe(['actif' => true, 'jours_avant' => 21, 'heure' => '18:00'])
        ->and(ReglagesRelances::paiement())->toBe(['actif' => false, 'heures_apres' => 48])
        ->and(Reglage::count())->toBe(2);
});

test("les listes montrent l'objet lisible, sans accolades", function () {
    $this->actingAs(admin(2))->post('/admin/relances', relance(['sujet' => '{evenement} : bonjour {prenom}', 'quand' => 'date', 'date_jour' => today()->addDays(3)->toDateString(), 'heure' => '10:00']))
        ->assertSessionHas('statut', fn ($m) => str_contains($m, $this->evenement->nom.' : bonjour [prénom]'));

    $this->get('/admin/relances')->assertSee($this->evenement->nom.' : bonjour [prénom]')->assertDontSee('{evenement}');
});

test("l'aperçu d'un envoi programmé donne la vraie date", function () {
    inscriptionPayee(User::factory()->create(), $this->course);
    $jour = $this->evenement->date_evenement->copy()->subDays(7);

    $this->actingAs(admin(2))->post('/admin/relances/apercu', relance(['quand' => 'avant', 'jours_avant' => 7, 'heure' => '10:00']))
        ->assertSee('le '.$jour->translatedFormat('l j F Y').' à 10h00 (7 jour(s) avant l&#039;événement)', false)
        ->assertSee('Programmer l&#039;envoi', false);
});
