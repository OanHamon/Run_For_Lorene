<?php

use App\Models\Commande;
use App\Models\Don;
use App\Models\User;

beforeEach(function () {
    $this->seed();

    $commande = fn (string $statut, ?User $user = null) => Commande::create([
        'date_commande' => now(),
        'montant_total' => 50,
        'statut' => $statut,
        'id_user' => ($user ?? User::factory()->create())->id_user,
    ]);

    Don::create(['montant' => 50, 'recu_fiscal_demande' => true, 'id_commande' => $commande(Commande::PAYEE, User::factory()->create(['nom' => 'Généreux']))->id_commande]);
    Don::create(['montant' => 20, 'anonyme' => true, 'id_commande' => $commande(Commande::PAYEE, User::factory()->create(['nom' => 'Discret']))->id_commande]);
    Don::create(['montant' => 99, 'id_commande' => $commande(Commande::EN_COURS, User::factory()->create(['nom' => 'Impayé']))->id_commande]);
});

test('la liste des dons ne compte que les commandes payées', function () {
    $this->actingAs(admin(1))->get('/admin/dons')
        ->assertOk()
        ->assertSee('70 €')
        ->assertSee('Généreux')
        ->assertSee('Anonyme')
        ->assertDontSee('Impayé');
});

test('le filtre « reçu demandé » et l\'export CSV', function () {
    $this->actingAs(admin(1));

    $this->get('/admin/dons?recu=1')->assertSee('Généreux')->assertDontSee('Discret');

    $csv = lireCsv($this->get('/admin/dons/export?recu=1')->assertOk()->streamedContent());
    expect($csv)->toStartWith("sep=;\r\nN°;Date;Montant;")
        ->and($csv)->toContain(';50,00;Généreux;')
        ->and($csv)->not->toContain('Discret');
});
