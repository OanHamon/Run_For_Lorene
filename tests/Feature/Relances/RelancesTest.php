<?php

use App\Models\Commande;
use App\Models\Course;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\Reglage;
use App\Models\User;
use App\Notifications\InscriptionValidee;
use App\Notifications\RelanceInscription;
use App\Services\Inscriptions\MettreAJourStatut;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed();
    $this->user = User::factory()->create();
    $this->marche = Course::where('nom', 'Marche 5 km')->first();
    $this->evenement = Evenement::courant();
});

test('la relance automatique des documents est en pause par défaut', function () {
    inscriptionPayee($this->user, $this->marche, Inscription::DOSSIER_INCOMPLET);

    $this->travelTo($this->evenement->date_evenement->copy()->subDays(15)->setTime(10, 0));
    $this->artisan('rfl:relancer-documents')->assertSuccessful();

    Notification::assertNothingSent();
});

test("relance documents, une fois activée : 15 jours avant l'événement, une seule fois, seulement les dossiers incomplets", function () {
    Reglage::ecrire('relance.documents', ['actif' => true, 'jours_avant' => 15, 'heure' => '10:00']);
    $incomplete = inscriptionPayee($this->user, $this->marche, Inscription::DOSSIER_INCOMPLET);
    $autre = User::factory()->create();
    inscriptionPayee($autre, $this->marche, Inscription::VALIDEE);

    $this->travelTo($this->evenement->date_evenement->copy()->subDays(20));
    $this->artisan('rfl:relancer-documents')->assertSuccessful();
    Notification::assertNothingSent();

    // Le jour J-15, rien avant l'heure d'envoi (10 h par défaut), puis une seule relance.
    $this->travelTo($this->evenement->date_evenement->copy()->subDays(15)->setTime(9, 0));
    $this->artisan('rfl:relancer-documents')->assertSuccessful();
    Notification::assertNothingSent();

    $this->travelTo($this->evenement->date_evenement->copy()->subDays(15)->setTime(10, 0));
    $this->artisan('rfl:relancer-documents')->assertSuccessful();
    $this->artisan('rfl:relancer-documents')->assertSuccessful();

    Notification::assertSentToTimes($this->user, RelanceInscription::class, 1);
    Notification::assertNotSentTo($autre, RelanceInscription::class);
    expect($incomplete->fresh()->statut)->toBe(Inscription::DOSSIER_INCOMPLET);
});

test('pas de relance de paiement : une commande non payée redevient un brouillon, sans email', function () {
    $inscription = inscriptionPayee($this->user, $this->marche, Inscription::EN_ATTENTE_PAIEMENT);
    $commande = $inscription->commande;
    $commande->update(['statut' => Commande::EN_COURS, 'date_commande' => now()]);
    Paiement::create(['prestataire' => 'simulation', 'reference' => 'sim_x', 'montant' => 10, 'statut' => Paiement::EN_ATTENTE, 'id_commande' => $commande->id_commande]);

    // Session de paiement encore ouverte : on n'y touche pas.
    $this->travel(20)->minutes();
    $this->artisan('rfl:abandonner-commandes')->assertSuccessful();
    expect($commande->fresh()->statut)->toBe(Commande::EN_COURS);

    // Session terminée (31 min) et marge passée : la commande est abandonnée, l'inscription libérée.
    $this->travel(20)->minutes();
    $this->artisan('rfl:abandonner-commandes')->assertSuccessful();

    expect($commande->fresh()->statut)->toBe(Commande::ABANDONNEE)
        ->and($inscription->fresh()->statut)->toBe(Inscription::BROUILLON)
        ->and($inscription->fresh()->id_commande)->toBeNull();
    Notification::assertNothingSent();
    $this->artisan('list')->doesntExpectOutputToContain('rfl:relancer-paiements');
});

test('une commande payée pendant la session n\'est jamais abandonnée', function () {
    $inscription = inscriptionPayee($this->user, $this->marche, Inscription::EN_ATTENTE_PAIEMENT);
    $commande = $inscription->commande;
    $commande->update(['statut' => Commande::EN_COURS, 'date_commande' => now()->subHour()]);
    Paiement::create(['prestataire' => 'simulation', 'reference' => 'sim_ok', 'montant' => 10, 'statut' => Paiement::REUSSI, 'id_commande' => $commande->id_commande]);

    $this->artisan('rfl:abandonner-commandes')->assertSuccessful();

    expect($commande->fresh()->statut)->toBe(Commande::EN_COURS)
        ->and($inscription->fresh()->statut)->toBe(Inscription::EN_ATTENTE_PAIEMENT);
});

test('dossier complété après le paiement : email « inscription validée » avec le dossard', function () {
    $inscription = documentsComplets(inscriptionPayee($this->user, $this->marche, Inscription::DOSSIER_INCOMPLET));

    app(MettreAJourStatut::class)($inscription);

    expect($inscription->fresh()->statut)->toBe(Inscription::VALIDEE);
    Notification::assertSentTo($this->user, InscriptionValidee::class, fn ($n) => str_contains($n->toMail($this->user)->subject, "Dossard n° {$inscription->fresh()->dossard}"));
});

test('les relances automatiques se règlent et se mettent en pause depuis l\'admin', function () {
    Reglage::ecrire('relance.documents', ['actif' => true, 'jours_avant' => 30, 'heure' => '18:00']);
    inscriptionPayee($this->user, $this->marche, Inscription::DOSSIER_INCOMPLET);

    $this->travelTo($this->evenement->date_evenement->copy()->subDays(30)->setTime(17, 0));
    $this->artisan('rfl:relancer-documents');
    Notification::assertNothingSent();

    $this->travelTo($this->evenement->date_evenement->copy()->subDays(30)->setTime(18, 5));
    $this->artisan('rfl:relancer-documents');
    Notification::assertSentToTimes($this->user, RelanceInscription::class, 1);
});
