<?php

use App\Models\Course;
use App\Models\Evenement;
use App\Models\JournalAdmin;
use App\Models\NoteInterne;
use App\Models\User;
use App\Services\Retrait\Retraits;
use App\Support\TachesPlanifiees;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed();
    $this->evenement = Evenement::first();
    $this->course = Course::where('id_evenement', $this->evenement->id_evenement)->first();
});

test('« Exports et listes » rassemble les fichiers à sortir', function () {
    $this->actingAs(admin(1))->get('/admin/exports')->assertOk()
        ->assertSee(route('admin.inscriptions.export'))
        ->assertSee(route('admin.inscriptions.dossards'))
        ->assertSee(route('admin.exports.retraits'))
        ->assertSee(route('admin.exports.emargement', ['course' => $this->course->id_course]));
});

test("la liste d'émargement est imprimable, par course et par ordre alphabétique, notée au journal", function () {
    inscriptionPayee(User::factory()->create(['prenom' => 'Zoé', 'nom' => 'Bernard']), $this->course);
    inscriptionPayee(User::factory()->create(['prenom' => 'Léo', 'nom' => 'Aubert']), $this->course);

    $this->actingAs(admin(1))->get('/admin/exports/emargement?course='.$this->course->id_course)->assertOk()
        ->assertSee($this->course->nom)
        ->assertSeeInOrder(['Aubert', 'Bernard'])
        ->assertSee('Signature')
        ->assertSee('window.print()', false);

    expect(JournalAdmin::where('action', 'export.emargement')->exists())->toBeTrue();
});

test("l'export des retraits dit qui a remis chaque dossard", function () {
    $i = inscriptionPayee(User::factory()->create(['prenom' => 'Ana', 'nom' => 'Costa']), $this->course);
    $i->update(['dossard' => 7]);
    $admin = admin(2);
    app(Retraits::class)->remettre($i->fresh(), 'Sacha (poste Entrée)');

    $csv = lireCsv($this->actingAs($admin)->get('/admin/exports/retraits')->assertOk()->streamedContent());
    expect($csv)->toContain('Costa')->toContain('oui')->toContain('Sacha (poste Entrée)');
});

test('les notes internes : niveau 2 pour écrire, visibles des admins, notées au journal', function () {
    $i = inscriptionPayee(User::factory()->create(), $this->course);

    $this->actingAs(admin(1))->post("/admin/inscriptions/{$i->id_inscription}/notes", ['texte' => 'x'])->assertForbidden();

    $this->actingAs($admin = admin(2))->post("/admin/inscriptions/{$i->id_inscription}/notes", ['texte' => 'Appelé le 12/03, certificat en attente'])
        ->assertRedirect(route('admin.inscriptions.show', $i).'#notes');

    expect(NoteInterne::sole()->id_admin)->toBe($admin->id_user)
        ->and(JournalAdmin::where('action', 'inscription.note')->exists())->toBeTrue();

    $this->actingAs(admin(1))->get(route('admin.inscriptions.show', $i))->assertSee('Appelé le 12/03, certificat en attente')->assertDontSee('Ajouter la note');
    $this->actingAs($i->participant->user, 'web')->get(route('espace.inscription', $i))->assertDontSee('Appelé le 12/03');
});

test("l'état technique montre la file, les tâches planifiées et les réglages, au super admin seulement", function () {
    DB::table('failed_jobs')->insert(['uuid' => 'abc', 'connection' => 'database', 'queue' => 'default', 'payload' => json_encode(['displayName' => 'App\\Notifications\\RelanceAdmin']), 'exception' => "RuntimeException: SMTP injoignable\n#0 trace", 'failed_at' => now()]);
    $tache = collect(app(Schedule::class)->events())->first(fn ($e) => TachesPlanifiees::nom($e) === 'rfl:relancer-documents');
    TachesPlanifiees::noter($tache, true);

    $this->actingAs(admin(2))->get('/admin/technique')->assertForbidden();
    $this->actingAs(admin(3))->get('/admin/technique')->assertOk()
        ->assertSee('RelanceAdmin')
        ->assertSee('SMTP injoignable')
        ->assertSee('rfl:relancer-documents')
        ->assertSee('rfl:purger-documents')
        ->assertSee('jamais vue')
        ->assertSee('Mode débogage');
});
