<?php

use App\Models\Commande;
use App\Models\Course;
use App\Models\JournalAdmin;
use App\Models\Paiement;
use App\Models\Remboursement;
use App\Models\User;
use App\Services\Paiement\PasserelleStripe;

beforeEach(function () {
    $this->seed();
    config(['paiement.passerelle' => 'simulation', 'services.stripe.webhook_secret' => 'whsec_test']);

    $this->payeur = User::factory()->create(['prenom' => 'Sam', 'nom' => 'Leroy']);
    $this->inscription = inscriptionPayee($this->payeur, Course::where('nom', 'Course 10 km')->first());
    $this->inscription->commande->update(['montant_total' => 25]);
    $this->paiement = Paiement::create([
        'prestataire' => 'simulation',
        'reference' => 'sim_paye',
        'montant' => 25,
        'frais' => 0.63,
        'statut' => Paiement::REUSSI,
        'date_paiement' => now(),
        'id_commande' => $this->inscription->id_commande,
    ]);
});

test('la liste et le détail des commandes montrent paiements et frais', function () {
    $this->actingAs(admin(1));

    $this->get('/admin/commandes')->assertOk()->assertSee('Sam Leroy')->assertSee('0,63 €');
    $this->get('/admin/commandes?q=sim_paye')->assertSee('Sam Leroy');
    $this->get("/admin/commandes/{$this->inscription->id_commande}")
        ->assertOk()
        ->assertSee('sim_paye')
        ->assertSee('Réussi')
        ->assertDontSee('Rembourser (jusqu'); // niveau 1 : pas de remboursement
});

test('un super admin rembourse en partie, puis le reste', function () {
    $this->actingAs($admin = admin(3));

    $this->post("/admin/paiements/{$this->paiement->id_paiement}/rembourser", ['montant' => '10', 'motif' => 'T-shirt non retiré'])
        ->assertSessionHasNoErrors();
    expect($this->paiement->remboursable())->toBe(15.0);

    $this->post("/admin/paiements/{$this->paiement->id_paiement}/rembourser", ['montant' => '15.01'])->assertSessionHasErrors('montant');
    $this->post("/admin/paiements/{$this->paiement->id_paiement}/rembourser", ['montant' => '15'])->assertSessionHasNoErrors();

    expect($this->paiement->remboursable())->toBe(0.0)
        ->and(Remboursement::where('id_paiement', $this->paiement->id_paiement)->sum('montant'))->toEqual(25)
        ->and(Remboursement::first()->id_admin)->toBe($admin->id_user)
        ->and(JournalAdmin::where('action', 'paiement.remboursement')->count())->toBe(2);

    // Le tableau de bord en tient compte.
    $this->get('/admin')->assertSee('Remboursé : 25 €');
});

test("un paiement non réussi ne se rembourse pas", function () {
    $this->paiement->update(['statut' => Paiement::ECHOUE]);

    $this->actingAs(admin(3))->post("/admin/paiements/{$this->paiement->id_paiement}/rembourser", ['montant' => '5'])
        ->assertSessionHasErrors('montant');

    expect(Remboursement::count())->toBe(0);
});

test('le remboursement demande le niveau 3', function () {
    $this->actingAs(admin(2))->post("/admin/paiements/{$this->paiement->id_paiement}/rembourser", ['montant' => '5'])->assertForbidden();
});

test('webhook charge.refunded : remboursement fait dans Stripe enregistré une seule fois', function () {
    $this->paiement->update(['prestataire' => 'stripe', 'reference' => 'cs_test_123']);

    $this->mock(PasserelleStripe::class, function ($mock) {
        $mock->shouldReceive('remboursementsDe')->with('ch_123')->andReturn([
            (object) ['id' => 're_1', 'amount' => 800, 'status' => 'succeeded', 'created' => time(), 'metadata' => (object) []],
        ]);
        $mock->shouldReceive('sessionDuPaiement')->with('pi_123')->andReturn('cs_test_123');
    });

    $envoyer = function () {
        $payload = json_encode([
            'id' => 'evt_'.uniqid(),
            'object' => 'event',
            'type' => 'charge.refunded',
            'data' => ['object' => ['object' => 'charge', 'id' => 'ch_123', 'payment_intent' => 'pi_123', 'amount_refunded' => 800]],
        ]);
        $t = time();

        return $this->call('POST', '/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', "{$t}.{$payload}", 'whsec_test'),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    };

    $envoyer()->assertOk();
    $envoyer()->assertOk();

    $remboursement = Remboursement::sole();
    expect($remboursement->reference)->toBe('re_1')
        ->and((float) $remboursement->montant)->toBe(8.0)
        ->and($remboursement->id_paiement)->toBe($this->paiement->id_paiement)
        ->and($remboursement->id_admin)->toBeNull();
});
