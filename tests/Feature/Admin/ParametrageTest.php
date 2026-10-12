<?php

use App\Models\Course;
use App\Models\Declinaison;
use App\Models\Evenement;
use App\Models\InscriptionDeclinaison;
use App\Models\JournalAdmin;
use App\Models\OptionEvenement;
use App\Models\Tarif;
use App\Models\TexteLegal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test("lot 2 : un admin crée de zéro une course fictive complète", function () {
    $this->actingAs($admin = admin(3));

    // 1. L'événement
    $this->get('/admin/evenements/nouveau')->assertOk();
    $this->post('/admin/evenements', [
        'nom' => 'Course fictive',
        'date_evenement' => now()->addMonths(3)->toDateString(),
        'ouverture' => now()->subDay()->format('Y-m-d\TH:i'),
        'fermeture' => now()->addMonths(2)->format('Y-m-d\TH:i'),
        'statut' => 'publie',
    ])->assertRedirect();

    $evenement = Evenement::where('nom', 'Course fictive')->firstOrFail();
    expect($evenement->inscriptionsOuvertes())->toBeTrue();

    // 2. La course et son tarif
    $this->post("/admin/evenements/{$evenement->id_evenement}/courses", [
        'nom' => '8 km fictifs',
        'heure_depart' => '10:15',
        'distance' => '8',
        'age_min' => '14',
        'age_max' => '',
        'quota' => '50',
        'dossard_debut' => '500',
        'dossard_fin' => '549',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $course = Course::where('nom', '8 km fictifs')->firstOrFail();
    expect($course->age_max)->toBeNull()->and($course->quota)->toBe(50);

    $this->post("/admin/courses/{$course->id_course}/tarifs", [
        'libelle' => 'Plein tarif',
        'montant' => '15.50',
        'valide_du' => today()->toDateString(),
        'valide_au' => $evenement->date_evenement->toDateString(),
        'age_min' => '',
        'age_max' => '',
    ])->assertSessionHasNoErrors();

    expect($course->tarifPour(30)?->montant)->toBe('15.50');

    // 3. Le t-shirt et ses tailles (écrans masqués par défaut, activés ici)
    config(['rfl.options' => true]);
    $this->post("/admin/evenements/{$evenement->id_evenement}/options", ['libelle' => 'T-shirt', 'prix' => '0'])->assertRedirect();
    $option = OptionEvenement::where('id_evenement', $evenement->id_evenement)->firstOrFail();
    $this->post("/admin/options/{$option->id_option}/declinaisons", ['libelle' => 'M', 'stock' => '10']);
    $this->post("/admin/options/{$option->id_option}/declinaisons", ['libelle' => 'L', 'stock' => '']);
    expect($option->declinaisons()->pluck('stock', 'libelle')->all())->toBe(['M' => 10, 'L' => null]);

    // 4. Les textes légaux
    foreach (['reglement', 'decharge', 'rgpd', 'droit_image'] as $type) {
        $this->post("/admin/evenements/{$evenement->id_evenement}/textes", [
            'type_texte' => $type,
            'fichier' => UploadedFile::fake()->create("{$type}.pdf", 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();
    }
    expect(TexteLegal::where('id_evenement', $evenement->id_evenement)->count())->toBe(4);
    Storage::disk('local')->assertExists(TexteLegal::chemin($evenement, 'reglement', 1));

    // Tout apparaît dans la fiche de l'événement, et chaque action est tracée.
    $this->get("/admin/evenements/{$evenement->id_evenement}?onglet=courses")->assertSee('8 km fictifs')->assertSee('15,50 €');
    $this->get("/admin/evenements/{$evenement->id_evenement}?onglet=options")->assertSee('T-shirt');
    $this->get("/admin/evenements/{$evenement->id_evenement}?onglet=textes")->assertSee('Règlement');
    expect(JournalAdmin::where('id_admin', $admin->id_user)->count())->toBeGreaterThanOrEqual(8);
});

test('une nouvelle version de texte légal ne remplace pas la précédente', function () {
    $this->seed();
    $evenement = Evenement::first();

    $this->actingAs(admin(3))->post("/admin/evenements/{$evenement->id_evenement}/textes", [
        'type_texte' => 'reglement',
        'fichier' => UploadedFile::fake()->create('reglement.pdf', 50, 'application/pdf'),
    ]);

    $versions = TexteLegal::where('id_evenement', $evenement->id_evenement)->where('type_texte', 'reglement')->orderBy('version')->pluck('version')->all();
    expect($versions)->toBe([1, 2]);
});

test('seul un PDF est accepté comme texte légal', function () {
    $this->seed();
    $evenement = Evenement::first();

    $this->actingAs(admin(3))->post("/admin/evenements/{$evenement->id_evenement}/textes", [
        'type_texte' => 'reglement',
        'fichier' => UploadedFile::fake()->create('reglement.docx', 50, 'application/msword'),
    ])->assertSessionHasErrorsIn('texte', 'fichier');
});

test('les plages de dossards de deux courses ne se chevauchent pas', function () {
    $this->seed();
    $evenement = Evenement::first();

    $this->actingAs(admin(3))->post("/admin/evenements/{$evenement->id_evenement}/courses", [
        'nom' => 'Chevauchement',
        'heure_depart' => '12:00',
        'dossard_debut' => '1990',
        'dossard_fin' => '2010',
    ])->assertSessionHasErrors(['dossard_debut' => 'Cette plage chevauche celle de « Course 10 km » (1000–1999).']);

    $this->post("/admin/evenements/{$evenement->id_evenement}/courses", [
        'nom' => 'Sans souci',
        'heure_depart' => '12:00',
        'dossard_debut' => '5000',
        'dossard_fin' => '5099',
    ])->assertSessionHasNoErrors();
});

test("une plage ne peut pas exclure des dossards déjà attribués", function () {
    $this->seed();
    $course = Course::where('nom', 'Course 10 km')->first();
    inscriptionPayee(User::factory()->create(), $course)->update(['dossard' => 1500]);

    $this->actingAs(admin(3))->put("/admin/courses/{$course->id_course}", [
        ...$course->only(['nom', 'age_min', 'quota']),
        'heure_depart' => '10:00',
        'dossard_debut' => '1000',
        'dossard_fin' => '1100',
    ])->assertSessionHasErrors('dossard_debut');
});

test("règles d'âge : le maximum ne peut pas être inférieur au minimum", function () {
    $this->seed();
    $course = Course::first();

    $this->actingAs(admin(3))->put("/admin/courses/{$course->id_course}", [
        'nom' => $course->nom,
        'heure_depart' => '10:00',
        'age_min' => '18',
        'age_max' => '12',
    ])->assertSessionHasErrors('age_max');
});

test("une course, un tarif ou une taille déjà utilisés ne se suppriment pas", function () {
    $this->seed();
    $course = Course::where('nom', 'Course 5 km')->first();
    $inscription = inscriptionPayee(User::factory()->create(), $course);
    $taille = Declinaison::first();
    InscriptionDeclinaison::create(['id_inscription' => $inscription->id_inscription, 'id_declinaison' => $taille->id_declinaison, 'quantite' => 1, 'prix_unitaire' => 0]);

    config(['rfl.options' => true]);
    $this->actingAs(admin(3));
    $this->delete("/admin/courses/{$course->id_course}")->assertSessionHas('erreur');
    $this->delete("/admin/tarifs/{$inscription->id_tarif}")->assertSessionHas('erreur');
    $this->delete("/admin/declinaisons/{$taille->id_declinaison}")->assertSessionHas('erreur');
    $this->delete("/admin/options/{$taille->id_option}")->assertSessionHas('erreur');

    expect(Course::find($course->id_course))->not->toBeNull()
        ->and(Tarif::find($inscription->id_tarif))->not->toBeNull()
        ->and(Declinaison::find($taille->id_declinaison))->not->toBeNull();

    // Une course sans inscription part avec ses tarifs.
    $libre = Course::where('nom', 'Marche 5 km')->first();
    $this->delete("/admin/courses/{$libre->id_course}")->assertSessionHas('statut');
    expect(Course::find($libre->id_course))->toBeNull()
        ->and(Tarif::where('id_course', $libre->id_course)->exists())->toBeFalse();
});

test("dupliquer un événement copie courses, tarifs, options et textes, un an plus tard", function () {
    $this->seed();
    $source = Evenement::first();
    Storage::disk('local')->put('textes-legaux/reglement-rfl3-v1.pdf', '%PDF');

    $this->actingAs(admin(3))->post("/admin/evenements/{$source->id_evenement}/dupliquer")->assertRedirect();

    $copie = Evenement::whereKeyNot($source->id_evenement)->firstOrFail();
    expect($copie->statut)->toBe('brouillon')
        ->and($copie->date_evenement->toDateString())->toBe('2028-04-03')
        ->and($copie->courses()->count())->toBe(4)
        ->and($copie->optionEvenements()->first()->declinaisons()->count())->toBe(5)
        ->and($copie->texteLegals()->pluck('version')->unique()->all())->toBe([1]);

    $tarif = Tarif::whereIn('id_course', $copie->courses()->select('id_course'))->first();
    expect($tarif->valide_au->toDateString())->toBe('2028-04-03');

    Storage::disk('local')->assertExists(TexteLegal::chemin($copie, 'reglement', 1));
});

test("le paramétrage est en lecture seule sous le niveau 3", function () {
    $this->seed();
    $evenement = Evenement::first();
    $course = Course::first();

    $this->actingAs(admin(2));
    $this->get("/admin/evenements/{$evenement->id_evenement}")->assertOk()->assertDontSee('Dupliquer pour l');
    $this->get("/admin/courses/{$course->id_course}")->assertOk();
    $this->get('/admin/evenements/nouveau')->assertForbidden();
    $this->put("/admin/courses/{$course->id_course}", ['nom' => 'Piraté', 'heure_depart' => '10:00'])->assertForbidden();
    $this->post("/admin/evenements/{$evenement->id_evenement}/dupliquer")->assertForbidden();

    expect($course->fresh()->nom)->not->toBe('Piraté');
});

test("chaque page du paramétrage s'affiche", function () {
    $this->seed();
    $evenement = Evenement::first();
    $course = Course::first();
    $option = OptionEvenement::first();
    config(['rfl.options' => true]);

    $this->actingAs(admin(3));
    $this->get('/admin/evenements')->assertOk()->assertSee('Run For Lorène 3');
    foreach (['general', 'courses', 'options', 'textes'] as $onglet) {
        $this->get("/admin/evenements/{$evenement->id_evenement}?onglet={$onglet}")->assertOk();
    }
    $this->get("/admin/evenements/{$evenement->id_evenement}/courses/nouvelle")->assertOk();
    $this->get("/admin/courses/{$course->id_course}")->assertOk()->assertSee('Plein tarif');
    $this->get("/admin/tarifs/{$course->tarifs()->first()->id_tarif}")->assertOk();
    $this->get("/admin/evenements/{$evenement->id_evenement}/options/nouvelle")->assertOk();
    $this->get("/admin/options/{$option->id_option}")->assertOk()->assertSee('XS');
});

test("les options (t-shirts) sont masquées tant qu'elles ne sont pas activées", function () {
    $this->seed();
    $evenement = Evenement::first();
    $option = OptionEvenement::first();
    inscriptionPayee(User::factory()->create(), Course::first());

    $this->actingAs(admin(3));
    $this->get("/admin/evenements/{$evenement->id_evenement}")->assertOk()->assertDontSee('Options et tailles');
    $this->get("/admin/evenements/{$evenement->id_evenement}?onglet=options")->assertOk()->assertDontSee('Ajouter une option');
    $this->get("/admin/options/{$option->id_option}")->assertNotFound();
    $this->post("/admin/evenements/{$evenement->id_evenement}/options", ['libelle' => 'Casquette', 'prix' => '5'])->assertNotFound();
    $this->get('/admin')->assertDontSee('Options vendues');
    expect($this->get('/admin/inscriptions/export')->streamedContent())->not->toContain('Options');

    config(['rfl.options' => true]);
    $this->get('/admin')->assertSee('Options vendues');
    $this->get("/admin/evenements/{$evenement->id_evenement}")->assertSee('Options et tailles');
});
