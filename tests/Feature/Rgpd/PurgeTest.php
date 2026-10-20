<?php

use App\Models\Consentement;
use App\Models\Course;
use App\Models\Document;
use App\Models\Inscription;
use App\Models\ReponseSante;
use App\Models\User;
use App\Services\Inscriptions\Questionnaires;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed();

    $this->inscription = documentsComplets(inscriptionPayee(User::factory()->create(), Course::where('nom', 'Marche 5 km')->first()));
    $this->evenement = $this->inscription->evenement;

    // Une version archivée (remplie deux fois) : elle doit partir aussi.
    $questionnaires = app(Questionnaires::class);
    $questionnaires->enregistrer($this->inscription, 'sante', reponses('sante'), true, $this->inscription->id_participant);
    $questionnaires->enregistrer($this->inscription, 'sante', reponses('sante', 'oui'), true, $this->inscription->id_participant);
});

test("rien n'est purgé moins de 12 mois après l'événement", function () {
    $this->travelTo(Carbon::parse($this->evenement->date_evenement)->addMonths(12)->subDay());

    $this->artisan('rfl:purger-documents')->assertSuccessful();

    expect(Document::count())->toBeGreaterThan(0)
        ->and($this->evenement->fresh()->documents_purges_le)->toBeNull();
});

test("12 mois après l'événement, documents, consentements et réponses de santé sont supprimés", function () {
    expect(Document::onlyTrashed()->count())->toBeGreaterThan(0);

    $this->travelTo(Carbon::parse($this->evenement->date_evenement)->addMonths(12));

    $this->artisan('rfl:purger-documents')->assertSuccessful();

    expect(Document::withTrashed()->count())->toBe(0)
        ->and(Consentement::count())->toBe(0)
        ->and(ReponseSante::count())->toBe(0)
        ->and($this->evenement->fresh()->documents_purges_le)->not->toBeNull();

    // L'inscription reste (comptabilité), sans rien de « manquant ».
    $inscription = Inscription::find($this->inscription->id_inscription);
    expect($inscription->statut)->toBe(Inscription::VALIDEE)
        ->and($inscription->documentsManquants())->toBe([])
        ->and($inscription->consentementsComplets())->toBeTrue();

    // Deuxième passage : rien à refaire.
    $this->artisan('rfl:purger-documents')->expectsOutput('Aucun événement à purger.');
});

test('la purge est planifiée chaque nuit', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('rfl:purger-documents');
});
