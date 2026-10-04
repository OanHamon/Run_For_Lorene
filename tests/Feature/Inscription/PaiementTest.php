<?php

use App\Models\Commande;
use App\Models\Course;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\User;
use App\Notifications\CommandeConfirmee;
use App\Services\Inscriptions\AttribuerDossard;
use App\Services\Paiement\EnregistrerPaiement;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;

beforeEach(function () {
    Notification::fake();
    $this->seed();
    config([
        'paiement.passerelle' => 'simulation',
        'services.stripe.webhook_secret' => 'whsec_test',
    ]);

    $this->user = User::factory()->create();
    $this->marche = Course::where('nom', 'Marche 5 km')->first();
});

/** Commande en attente de paiement pour $user sur $course, avec une tentative ouverte. */
function commandeEnAttente(User $user, Course $course): array
{
    test()->actingAs($user);

    $tunnel = Volt::test('inscription.tunnel')
        ->call('choisirParticipant', 'moi')
        ->set('dateNaissance', '1985-06-01')
        ->call('participantSuivant')
        ->call('choisirCourse', $course->id_course)
        ->call('epreuveSuivant')
        ->set(documentsRemplis())
        ->call('documentsSuivant')
        ->call('donPasser')
        ->call('payer')
        ->assertHasNoErrors();

    $commande = Commande::where('id_user', $user->id_user)->latest('id_commande')->first();

    return [$commande, $commande->paiements()->latest('id_paiement')->first()];
}

function webhookStripe(string $type, array $session, string $secret = 'whsec_test')
{
    $payload = json_encode([
        'id' => 'evt_'.uniqid(),
        'object' => 'event',
        'type' => $type,
        'data' => ['object' => array_merge(['object' => 'checkout.session', 'payment_intent' => null], $session)],
    ]);
    $t = time();
    $signature = "t={$t},v1=".hash_hmac('sha256', "{$t}.{$payload}", $secret);

    return test()->call('POST', '/stripe/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);
}

test('webhook : signature invalide refusée', function () {
    [, $paiement] = commandeEnAttente($this->user, $this->marche);

    webhookStripe('checkout.session.completed', ['id' => $paiement->reference, 'payment_status' => 'paid', 'amount_total' => 1200], 'whsec_autre')
        ->assertStatus(400);

    expect($paiement->fresh()->statut)->toBe(Paiement::EN_ATTENTE);
});

test('webhook : paiement réussi, et un événement reçu deux fois ne change rien', function () {
    [$commande, $paiement] = commandeEnAttente($this->user, $this->marche);

    webhookStripe('checkout.session.completed', ['id' => $paiement->reference, 'payment_status' => 'paid', 'amount_total' => 1200])->assertOk();
    $dossard = Inscription::sole()->dossard;
    webhookStripe('checkout.session.completed', ['id' => $paiement->reference, 'payment_status' => 'paid', 'amount_total' => 1200])->assertOk();

    expect($paiement->fresh()->statut)->toBe(Paiement::REUSSI)
        ->and((float) $paiement->fresh()->montant)->toBe(12.0)
        ->and($commande->fresh()->statut)->toBe(Commande::PAYEE)
        ->and(Inscription::sole()->statut)->toBe(Inscription::VALIDEE)
        ->and(Inscription::sole()->dossard)->toBe($dossard)->toBe(3000);

    Notification::assertSentToTimes($this->user, CommandeConfirmee::class, 1);
});

test("webhook : session expirée → l'inscription reste en attente de paiement et la place est libérée", function () {
    $this->marche->update(['quota' => 5]);
    [$commande, $paiement] = commandeEnAttente($this->user, $this->marche);
    expect($this->marche->placesRestantes())->toBe(4);

    webhookStripe('checkout.session.expired', ['id' => $paiement->reference, 'payment_status' => 'unpaid', 'amount_total' => 1200])->assertOk();

    expect($paiement->fresh()->statut)->toBe(Paiement::ECHOUE)
        ->and(Inscription::sole()->statut)->toBe(Inscription::EN_ATTENTE_PAIEMENT)
        ->and($commande->fresh()->statut)->toBe(Commande::EN_COURS)
        ->and($this->marche->placesRestantes())->toBe(5);
});

test('la place réservée pendant le paiement expire après 30 minutes', function () {
    $this->marche->update(['quota' => 1]);
    commandeEnAttente($this->user, $this->marche);
    expect($this->marche->placesRestantes())->toBe(0);

    $this->travel(31)->minutes();
    expect($this->marche->placesRestantes())->toBe(1);
});

test('paiement échoué puis « Réessayer » : nouvelle tentative, l\'ancienne est fermée', function () {
    [$commande, $paiement] = commandeEnAttente($this->user, $this->marche);

    $this->post(route('paiement.simuler', [$paiement, 'echec']))->assertRedirect(route('inscription.echec', $commande));
    $this->get(route('inscription.echec', $commande))->assertOk()->assertSee('Réessayer');
    expect(Inscription::sole()->statut)->toBe(Inscription::EN_ATTENTE_PAIEMENT);

    $this->post(route('inscription.payer', $commande))->assertRedirect();
    $nouveau = $commande->paiements()->latest('id_paiement')->first();
    expect($nouveau->id_paiement)->not->toBe($paiement->id_paiement)
        ->and($nouveau->statut)->toBe(Paiement::EN_ATTENTE)
        ->and($commande->paiements()->where('statut', Paiement::EN_ATTENTE)->count())->toBe(1);

    $this->post(route('paiement.simuler', [$nouveau, 'reussi']))->assertRedirect(route('inscription.confirmation', $commande));
    expect(Inscription::sole()->statut)->toBe(Inscription::VALIDEE);
});

test('relance impossible si l\'épreuve est devenue complète entre-temps', function () {
    $this->marche->update(['quota' => 1]);
    [$commande] = commandeEnAttente($this->user, $this->marche);

    $this->travel(31)->minutes();
    inscriptionPayee(User::factory()->create(), $this->marche);

    $this->actingAs($this->user)->post(route('inscription.payer', $commande))
        ->assertRedirect(route('inscription.echec', $commande))
        ->assertSessionHas('erreur');
});

test('« Modifier ma commande » remet les inscriptions en brouillon et garde le don', function () {
    [$commande] = commandeEnAttente($this->user, $this->marche);
    $commande->don()->create(['montant' => 15]);

    $this->post(route('inscription.modifier', $commande))->assertRedirect(route('inscription.tunnel'));

    expect($commande->fresh()->statut)->toBe(Commande::ABANDONNEE)
        ->and(Inscription::sole()->statut)->toBe(Inscription::BROUILLON)
        ->and(Inscription::sole()->id_commande)->toBeNull()
        ->and($commande->paiements()->sole()->statut)->toBe(Paiement::ECHOUE)
        ->and(session('tunnel.don'))->toBe('15');

    Volt::test('inscription.tunnel')->assertSet('etape', 'recap')->assertSet('don', '15');
});

test("on ne peut ni voir ni payer la commande d'un autre compte", function () {
    [$commande] = commandeEnAttente($this->user, $this->marche);

    $this->actingAs(User::factory()->create());
    $this->get(route('inscription.confirmation', $commande))->assertNotFound();
    $this->post(route('inscription.payer', $commande))->assertNotFound();
    $this->post(route('inscription.modifier', $commande))->assertNotFound();
});

test('paiement reçu en double : la seconde tentative ne revalide rien', function () {
    [$commande, $paiement] = commandeEnAttente($this->user, $this->marche);
    $this->post(route('inscription.payer', $commande));
    $second = $commande->paiements()->latest('id_paiement')->first();

    app(EnregistrerPaiement::class)->reussi($second->reference, 12.0);
    app(EnregistrerPaiement::class)->reussi($paiement->reference, 12.0);

    expect($commande->paiements()->where('statut', Paiement::REUSSI)->count())->toBe(2)
        ->and(Inscription::whereNotNull('dossard')->count())->toBe(1);
    Notification::assertSentToTimes($this->user, CommandeConfirmee::class, 1);
});

test('dossards : premier numéro libre de la plage, uniques, et alerte quand la plage est pleine', function () {
    $this->marche->update(['dossard_debut' => 10, 'dossard_fin' => 12]);
    $attribuer = app(AttribuerDossard::class);

    $a = inscriptionPayee(User::factory()->create(), $this->marche);
    $b = inscriptionPayee(User::factory()->create(), $this->marche);
    $c = inscriptionPayee(User::factory()->create(), $this->marche);
    $d = inscriptionPayee(User::factory()->create(), $this->marche);

    expect($attribuer($a))->toBe(10)->and($attribuer($b))->toBe(11);

    $a->update(['dossard' => null]); // numéro libéré (ex. annulation)
    expect($attribuer($c))->toBe(10)
        ->and($attribuer($d))->toBe(12)
        ->and($attribuer(inscriptionPayee(User::factory()->create(), $this->marche)))->toBeNull();
});

test('deux participants du panier sur la dernière place : la commande est refusée', function () {
    $this->marche->update(['quota' => 1]);
    $this->actingAs($this->user);

    $tunnel = Volt::test('inscription.tunnel');
    foreach ([['moi', 'Sam', '1985-06-01'], ['nouveau', 'Lou', '2015-01-01']] as [$choix, $prenom, $naissance]) {
        $tunnel->call('ajouterParticipant')
            ->call('choisirParticipant', $choix)
            ->set('prenom', $prenom)
            ->set('dateNaissance', $naissance)
            ->call('participantSuivant')
            ->call('choisirCourse', $this->marche->id_course)
            ->call('epreuveSuivant')
            ->set(documentsRemplis())
            ->call('documentsSuivant');
    }

    $tunnel->call('donPasser')->call('payer')->assertHasErrors('commande');
    expect(Commande::count())->toBe(0)
        ->and(Inscription::where('statut', Inscription::BROUILLON)->count())->toBe(2);
});

test('la page de simulation est inaccessible quand Stripe est configuré', function () {
    [, $paiement] = commandeEnAttente($this->user, $this->marche);
    config(['paiement.passerelle' => 'stripe']);

    $this->get(route('paiement.simulation', $paiement))->assertNotFound();
});
