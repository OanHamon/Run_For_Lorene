<?php

use App\Models\CandidatureBenevole;
use App\Models\Evenement;
use App\Models\JournalAdmin;
use App\Models\User;
use App\Notifications\CandidatureRecue;
use App\Notifications\CandidatureStatutModifie;
use App\Services\Admin\SupprimerCompte;
use App\Services\Inscriptions\PurgerDocuments;
use App\Support\Benevolat;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    $this->seed();
    Storage::fake('local');
    Notification::fake();
    Benevolat::ouvrir(true);
    $this->user = User::factory()->create(['prenom' => 'Lou', 'nom' => 'Martin', 'telephone' => null]);
    $this->evenement = Evenement::courant();
});

/** Remplit les quatre premiers panneaux ; $age règle le panneau « Toi ». */
function candidatureRemplie(string $age = 'majeur')
{
    $f = Volt::test('benevoles.candidature')
        ->set('telephone', '06 12 34 56 78')
        ->set('age', $age)
        ->set('urgenceNom', 'Claire Martin')
        ->set('urgenceTel', '0700000000');

    if ($age !== 'majeur') {
        $f->set('responsableNom', 'Paul Martin')->set('responsableTel', '0611111111');
    }

    return $f->call('toiSuivant')->assertHasNoErrors()->assertSet('etape', 'missions')
        ->call('basculerMission', 'ravitaillement')
        ->call('basculerMission', 'communication')
        ->set('competences', ['photo'])
        ->set('impossibles', ['commissaires'])
        ->call('missionsSuivant')->assertHasNoErrors()->assertSet('etape', 'dispos')
        ->set('tranches', ['7_10', '10_12'])
        ->set('periodes.veille.etat', 'disponible')->set('periodes.veille.debut', '14:00')->set('periodes.veille.fin', '18:00')
        ->set('periodes.soir.etat', 'indisponible')
        ->set('periodes.lendemain.etat', 'a_preciser')
        ->set('vehicule', 'non')->set('binome', 'non')->set('court', 'non')
        ->call('disposSuivant')->assertHasNoErrors()->assertSet('etape', 'pratique')
        ->set('taille', 'M')->set('repas', 'oui')->set('allergies', 'oui')->set('allergiesDetail', 'Arachides')->set('soiree', 'oui')
        ->call('pratiqueSuivant')->assertHasNoErrors()->assertSet('etape', 'envoi');
}

test('la page bénévoles présente les missions et mène à la candidature', function () {
    $this->get('/benevoles')->assertOk()->assertSee('Deviens bénévole')->assertSee('Ravitaillement')->assertSee('Je propose mon aide');
    $this->get('/')->assertSee(route('benevoles'));

    Benevolat::ouvrir(false);
    $this->get('/benevoles')->assertSee('Les candidatures ouvrent bientôt')->assertDontSee('Je propose mon aide');
});

test('la candidature demande un compte vérifié', function () {
    $this->get('/benevoles/candidature')->assertRedirect(route('login'));
});

test('candidatures fermées : le formulaire ne s\'ouvre pas', function () {
    Benevolat::ouvrir(false);
    $this->actingAs($this->user);
    Volt::test('benevoles.candidature')->assertSet('etape', 'ferme');
});

test('une candidature complète est enregistrée, chiffrée et accusée par email', function () {
    $this->actingAs($this->user);

    candidatureRemplie()
        ->set('photo', UploadedFile::fake()->image('moi.jpg'))
        ->set('droitImage', 'non')
        ->call('envoyer')
        ->assertRedirect(route('espace.benevole'));

    $c = CandidatureBenevole::firstOrFail();
    expect($c->statut)->toBe(CandidatureBenevole::RECUE)
        ->and($c->missions_preferees)->toBe(['ravitaillement', 'communication'])
        ->and($c->missions_impossibles)->toBe(['commissaires'])
        ->and($c->competences)->toBe(['photo'])
        ->and($c->disponibilites['periodes']['veille'])->toEqual(['etat' => 'disponible', 'debut' => '14:00', 'fin' => '18:00'])
        ->and($c->allergies)->toBe('Arachides')
        ->and($c->droit_image)->toBeFalse()
        ->and($c->historique)->toHaveCount(1);

    Storage::disk('local')->assertExists($c->photo);
    // Données sensibles chiffrées en base.
    expect(DB::table('candidature_benevoles')->value('allergies'))->not->toContain('Arachides')
        ->and(DB::table('candidature_benevoles')->value('urgence_nom'))->not->toContain('Claire');
    // Le téléphone complète le compte qui n'en avait pas.
    expect($this->user->fresh()->telephone)->toBe('0612345678');

    Notification::assertSentTo($this->user, CandidatureRecue::class);
});

test('le brouillon reprend au dernier panneau atteint', function () {
    $this->actingAs($this->user);
    candidatureRemplie();

    Volt::test('benevoles.candidature')
        ->assertSet('etape', 'envoi')
        ->assertSet('preferees', ['ravitaillement', 'communication'])
        ->assertSet('allergiesDetail', 'Arachides');
});

test('une candidature envoyée n\'est plus modifiable', function () {
    $this->actingAs($this->user);
    candidatureRemplie()->set('droitImage', 'oui')->call('envoyer');

    Volt::test('benevoles.candidature')->assertRedirect(route('espace.benevole'));
    $this->get('/espace/benevole')->assertOk()->assertSee('Candidature reçue')->assertSee('Arachides');
});

test('il faut une mission et un créneau', function () {
    $this->actingAs($this->user);
    Volt::test('benevoles.candidature')
        ->set('telephone', '0612345678')->set('age', 'majeur')->set('urgenceNom', 'X')->set('urgenceTel', '0700000000')
        ->call('toiSuivant')
        ->call('missionsSuivant')->assertHasErrors('preferees');
});

test('mineur : responsable légal obligatoire, autorisation parentale avant 16 ans', function () {
    $this->actingAs($this->user);
    Volt::test('benevoles.candidature')
        ->set('telephone', '0612345678')->set('age', 'moins_16')->set('urgenceNom', 'X')->set('urgenceTel', '0700000000')
        ->call('toiSuivant')->assertHasErrors(['responsableNom', 'responsableTel']);

    candidatureRemplie('moins_16')
        ->assertSee('autorisation parentale')
        ->set('autorisation', UploadedFile::fake()->create('autorisation.pdf', 100, 'application/pdf'))
        ->assertHasNoErrors();

    $c = CandidatureBenevole::firstOrFail();
    expect($c->autorisationRequise())->toBeTrue()->and($c->autorisation_parentale)->not->toBeNull()
        ->and($c->responsable_nom)->toBe('Paul Martin');
});

test('on ne saute pas d\'étape pour envoyer', function () {
    $this->actingAs($this->user);
    Volt::test('benevoles.candidature')
        ->set('telephone', '0612345678')->set('age', 'majeur')->set('urgenceNom', 'X')->set('urgenceTel', '0700000000')
        ->call('toiSuivant')
        ->set('droitImage', 'oui')->call('envoyer')
        ->assertSet('etape', 'toi');

    expect(CandidatureBenevole::first()->statut)->toBe(CandidatureBenevole::BROUILLON);
});

test('la photo d\'un bénévole n\'est servie qu\'à lui', function () {
    $this->actingAs($this->user);
    candidatureRemplie()->set('photo', UploadedFile::fake()->image('moi.jpg'))->set('droitImage', 'oui')->call('envoyer');

    $this->get('/espace/benevole/photo')->assertOk();
    $this->actingAs(User::factory()->create())->get('/espace/benevole/photo')->assertNotFound();
});

test('admin niveau 1 : liste et fiche sans données sensibles', function () {
    $this->actingAs($this->user);
    candidatureRemplie()->set('droitImage', 'oui')->call('envoyer');
    $c = CandidatureBenevole::firstOrFail();

    $this->actingAs(admin(1));
    $this->get('/admin/benevoles')->assertOk()->assertSee('Lou Martin');
    $this->get("/admin/benevoles/{$c->id_candidature}")->assertOk()->assertSee('Ravitaillement')
        ->assertDontSee('Arachides')->assertDontSee('Claire Martin');
    $this->get('/admin/benevoles/export')->assertForbidden();
    $this->post("/admin/benevoles/{$c->id_candidature}/statut", ['statut' => 'confirmee'])->assertForbidden();
});

test('admin niveau 2 : décision, email, historique et journal', function () {
    $this->actingAs($this->user);
    candidatureRemplie()->set('droitImage', 'oui')->call('envoyer');
    $c = CandidatureBenevole::firstOrFail();

    $this->actingAs(admin(2));
    $this->get("/admin/benevoles/{$c->id_candidature}")->assertSee('Arachides')->assertSee('Claire Martin');

    $this->post("/admin/benevoles/{$c->id_candidature}/statut", [
        'statut' => 'confirmee', 'mission_affectee' => 'ravitaillement', 'message' => 'Rendez-vous à 7 h.', 'notifier' => '1',
    ])->assertRedirect();

    $c->refresh();
    expect($c->statut)->toBe('confirmee')->and($c->mission_affectee)->toBe('ravitaillement')
        ->and($c->historique()->first()->message)->toBe('Rendez-vous à 7 h.');
    Notification::assertSentTo($this->user, CandidatureStatutModifie::class);
    expect(JournalAdmin::where('action', 'benevole.statut')->exists())->toBeTrue();

    $this->actingAs($this->user)->get('/espace/benevole')->assertSee('Participation confirmée')->assertSee('Ravitaillement');

    $this->actingAs(admin(2));
    $csv = lireCsv($this->get('/admin/benevoles/export')->assertOk()->streamedContent());
    expect($csv)->toContain('Martin')->toContain('Arachides');
});

test('seul un super admin ouvre ou ferme les candidatures', function () {
    Benevolat::ouvrir(false);
    $this->actingAs(admin(2))->put('/admin/benevoles/ouverture', ['ouvert' => 1])->assertForbidden();
    $this->actingAs(admin(3))->put('/admin/benevoles/ouverture', ['ouvert' => 1])->assertRedirect();
    expect(Benevolat::ouvert())->toBeTrue();
});

test('la purge RGPD efface candidatures et fichiers', function () {
    $this->actingAs($this->user);
    candidatureRemplie()->set('photo', UploadedFile::fake()->image('moi.jpg'))->set('droitImage', 'oui')->call('envoyer');
    $photo = CandidatureBenevole::firstOrFail()->photo;

    $this->evenement->update(['date_evenement' => today()->subMonths(13)]);
    app(PurgerDocuments::class)();

    expect(CandidatureBenevole::count())->toBe(0);
    Storage::disk('local')->assertMissing($photo);
});

test('un compte avec une candidature envoyée ne se supprime pas', function () {
    $this->actingAs($this->user);
    candidatureRemplie()->set('droitImage', 'oui')->call('envoyer');

    expect(app(SupprimerCompte::class)->obstacles($this->user, admin(3)))->toContain('ce compte a envoyé une candidature bénévole');
});
