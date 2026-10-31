<?php

use App\Models\Course;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\JournalAdmin;
use App\Models\Relance;
use App\Models\User;
use App\Notifications\RelanceAdmin;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->seed();
    Notification::fake();
    $this->evenement = Evenement::first();
    $this->course = Course::where('id_evenement', $this->evenement->id_evenement)->first();
});

function envoiRelance(array $champs): TestResponse
{
    return test()->post('/admin/relances', $champs + ['modele' => 'libre', 'sujet' => 'Objet', 'message' => "Bonjour {prenom},\n\nCourse : {course}.", 'confirmation' => '1']);
}

test('les relances sont réservées au niveau 2', function () {
    $this->actingAs(admin(1))->get('/admin/relances')->assertForbidden();
    $this->actingAs(admin(2))->get('/admin/relances')->assertOk()->assertSee('Documents manquants');
});

test('un modèle préremplit objet, message et cible, tous modifiables', function () {
    $this->actingAs(admin(2))->get('/admin/relances/nouvelle?modele=documents')->assertOk()
        ->assertSee('Il manque un document pour prendre le départ')
        ->assertSee('{documents}')
        ->assertSee('<option value="dossier_incomplet" selected', false);
});

test("l'aperçu donne le nombre de destinataires et un exemple personnalisé", function () {
    $camille = User::factory()->create(['prenom' => 'Camille']);
    inscriptionPayee($camille, $this->course, Inscription::DOSSIER_INCOMPLET);
    inscriptionPayee(User::factory()->create(), $this->course); // validée : hors cible

    $this->actingAs(admin(2))->post('/admin/relances/apercu', [
        'modele' => 'documents', 'sujet' => 'Il manque {documents}', 'message' => "Bonjour {prenom},\n\nPour la {course}.", 'statut' => 'dossier_incomplet',
    ])->assertOk()
        ->assertSeeInOrder(['Aperçu', '1', 'destinataire(s)'])
        ->assertSee('Bonjour Camille,')
        ->assertSee('Pour la '.$this->course->nom.'.')
        ->assertSee('Il manque la décharge de responsabilité');

    Notification::assertNothingSent();
});

test("l'envoi part au titulaire de chaque inscription ciblée, enregistré et noté au journal", function () {
    $a = inscriptionPayee($ua = User::factory()->create(['prenom' => 'Alix']), $this->course, Inscription::DOSSIER_INCOMPLET);
    $b = inscriptionPayee($ub = User::factory()->create(), $this->course, Inscription::DOSSIER_INCOMPLET);

    $this->actingAs($admin = admin(2));
    envoiRelance(['statut' => 'dossier_incomplet'])->assertRedirect('/admin/relances')->assertSessionHas('statut');

    Notification::assertSentTo($ua, RelanceAdmin::class, fn (RelanceAdmin $n) => $n->message === "Bonjour Alix,\n\nCourse : {$this->course->nom}." && $n->toMail($ua)->greeting === 'Bonjour Alix,');
    Notification::assertSentTo($ub, RelanceAdmin::class);

    $relance = Relance::sole();
    expect($relance->nb_destinataires)->toBe(2)
        ->and($relance->inscriptions->pluck('id_inscription')->sort()->values()->all())->toBe([$a->id_inscription, $b->id_inscription])
        ->and(JournalAdmin::where('action', 'relance.envoi')->where('id_admin', $admin->id_user)->exists())->toBeTrue();

    $this->get(route('admin.inscriptions.show', $a))->assertSee('Relances reçues')->assertSee('Objet');
});

test('on peut écrire à une seule personne, depuis sa fiche', function () {
    $cible = inscriptionPayee($u = User::factory()->create(['prenom' => 'Noa']), $this->course);
    inscriptionPayee($autre = User::factory()->create(), $this->course);

    $this->actingAs(admin(2));
    $this->get(route('admin.inscriptions.show', $cible))->assertSee(route('admin.relances.create', ['inscription' => $cible->id_inscription]));
    $this->get('/admin/relances/nouvelle?inscription='.$cible->id_inscription)->assertOk()->assertSee('Une seule personne');

    envoiRelance(['inscription' => $cible->id_inscription, 'statut' => 'validee']);

    Notification::assertSentTo($u, RelanceAdmin::class);
    Notification::assertNotSentTo($autre, RelanceAdmin::class);
});

test("une inscription non finalisée n'est jamais relancée", function () {
    $attente = inscriptionPayee($u1 = User::factory()->create(), $this->course, Inscription::EN_ATTENTE_PAIEMENT);
    inscriptionPayee($u2 = User::factory()->create(), $this->course, Inscription::BROUILLON);

    $this->actingAs(admin(2));
    envoiRelance([]);
    envoiRelance(['inscription' => $attente->id_inscription]);

    Notification::assertNothingSent();
    expect(Relance::pluck('nb_destinataires')->all())->toBe([0, 0]);
});

test('déjà relancé il y a moins de 48 h : signalé à l\'aperçu, écarté sauf choix contraire', function () {
    inscriptionPayee($u = User::factory()->create(['prenom' => 'Lou', 'nom' => 'Martel']), $this->course);
    $this->actingAs(admin(2));
    envoiRelance(['statut' => 'validee']);
    Notification::assertSentToTimes($u, RelanceAdmin::class, 1);

    $this->post('/admin/relances/apercu', ['modele' => 'libre', 'sujet' => 'Encore', 'message' => 'Bonjour', 'statut' => 'validee'])
        ->assertSee('ont déjà reçu une relance')->assertSee('Lou Martel');

    envoiRelance(['statut' => 'validee']);
    Notification::assertSentToTimes($u, RelanceAdmin::class, 1);

    envoiRelance(['statut' => 'validee', 'inclure_recents' => '1']);
    Notification::assertSentToTimes($u, RelanceAdmin::class, 2);

    $this->travel(49)->hours();
    adminConfirme(); // la session admin dure 8 h
    envoiRelance(['statut' => 'validee']);
    Notification::assertSentToTimes($u, RelanceAdmin::class, 3);
});

test('sans la case de confirmation, rien ne part', function () {
    inscriptionPayee(User::factory()->create(), $this->course);

    $this->actingAs(admin(2))->post('/admin/relances', ['modele' => 'libre', 'sujet' => 'Objet', 'message' => 'Bonjour', 'statut' => 'validee'])
        ->assertSessionHasErrors('confirmation');

    Notification::assertNothingSent();
    expect(Relance::count())->toBe(0);
});
