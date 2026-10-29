<?php

use App\Models\Commande;
use App\Models\Course;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\Paiement;
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

test("relance documents : 15 jours avant l'événement, une seule fois, seulement les dossiers incomplets", function () {
    $incomplete = inscriptionPayee($this->user, $this->marche, Inscription::DOSSIER_INCOMPLET);
    $autre = User::factory()->create();
    inscriptionPayee($autre, $this->marche, Inscription::VALIDEE);

    $this->travelTo($this->evenement->date_evenement->copy()->subDays(20));
    $this->artisan('rfl:relancer-documents')->assertSuccessful();
    Notification::assertNothingSent();

    $this->travelTo($this->evenement->date_evenement->copy()->subDays(15));
    $this->artisan('rfl:relancer-documents')->assertSuccessful();
    $this->artisan('rfl:relancer-documents')->assertSuccessful();

    Notification::assertSentToTimes($this->user, RelanceInscription::class, 1);
    Notification::assertNotSentTo($autre, RelanceInscription::class);
    expect($incomplete->fresh()->statut)->toBe(Inscription::DOSSIER_INCOMPLET);
});

test('relance paiement : 24 h après une tentative non aboutie, une seule fois, jamais pendant un paiement en cours', function () {
    $inscription = inscriptionPayee($this->user, $this->marche, Inscription::EN_ATTENTE_PAIEMENT);
    $commande = $inscription->commande;
    $commande->update(['statut' => Commande::EN_COURS]);
    $paiement = Paiement::create(['prestataire' => 'simulation', 'reference' => 'sim_x', 'montant' => 10, 'statut' => Paiement::ECHOUE, 'id_commande' => $commande->id_commande]);

    $this->artisan('rfl:relancer-paiements');
    Notification::assertNothingSent(); // échec il y a quelques secondes

    $this->travel(25)->hours();
    Paiement::create(['prestataire' => 'simulation', 'reference' => 'sim_y', 'montant' => 10, 'statut' => Paiement::EN_ATTENTE, 'id_commande' => $commande->id_commande]);
    $this->artisan('rfl:relancer-paiements');
    Notification::assertNothingSent(); // nouvelle tentative ouverte : la personne paie

    $this->travel(2)->hours();
    $this->artisan('rfl:relancer-paiements');
    $this->artisan('rfl:relancer-paiements');
    Notification::assertNothingSent(); // la tentative expirée date de moins de 24 h

    $this->travel(24)->hours();
    $this->artisan('rfl:relancer-paiements');
    $this->artisan('rfl:relancer-paiements');
    Notification::assertSentToTimes($this->user, RelanceInscription::class, 1);
});

test('dossier complété après le paiement : email « inscription validée » avec le dossard', function () {
    $inscription = documentsComplets(inscriptionPayee($this->user, $this->marche, Inscription::DOSSIER_INCOMPLET));

    app(MettreAJourStatut::class)($inscription);

    expect($inscription->fresh()->statut)->toBe(Inscription::VALIDEE);
    Notification::assertSentTo($this->user, InscriptionValidee::class, fn ($n) => str_contains($n->toMail($this->user)->subject, "Dossard n° {$inscription->fresh()->dossard}"));
});
