<?php

use App\Models\Commande;
use App\Models\Course;
use App\Models\Declinaison;
use App\Models\Don;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\InscriptionDeclinaison;
use App\Models\JournalAdmin;
use App\Models\User;
use App\Services\Admin\Statistiques;

beforeEach(function () {
    $this->seed();
    $this->evenement = Evenement::first();
    $this->course = fn (string $nom) => Course::where('nom', $nom)->first();
});

test("le back-office est réservé aux admins", function () {
    $this->get('/admin')->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();

    $this->actingAs(admin(1))->get('/admin')->assertOk()->assertSee('Tableau de bord');
});

test("un admin trouve le lien vers l'administration dans son espace, pas un participant", function () {
    $this->actingAs(admin(1))->get('/espace')->assertSee(route('admin.tableau'));
    $this->actingAs(User::factory()->create())->get('/espace')->assertDontSee(route('admin.tableau'));
});

test('les chiffres du tableau de bord correspondent aux données', function () {
    $dix = ($this->course)('Course 10 km');
    $cinq = ($this->course)('Course 5 km');

    $a = inscriptionPayee(User::factory()->create(), $dix);
    $b = inscriptionPayee(User::factory()->create(), $dix);
    $c = inscriptionPayee(User::factory()->create(), $cinq, Inscription::DOSSIER_INCOMPLET);
    $a->update(['prix_paye' => 25, 'dossard' => 1000]);
    $b->update(['prix_paye' => 25, 'dossard' => 1001]);
    $c->update(['prix_paye' => 18]);

    // En attente de paiement : ni recette ni inscrit réglé.
    $attente = inscriptionPayee(User::factory()->create(), $cinq, Inscription::EN_ATTENTE_PAIEMENT);
    $attente->commande->update(['statut' => Commande::EN_COURS]);

    // T-shirts : deux M (dont un payant) sur des inscriptions réglées, un L sur l'inscription en attente.
    $m = Declinaison::where('libelle', 'M')->first();
    $l = Declinaison::where('libelle', 'L')->first();
    InscriptionDeclinaison::create(['id_inscription' => $a->id_inscription, 'id_declinaison' => $m->id_declinaison, 'quantite' => 1, 'prix_unitaire' => 0]);
    InscriptionDeclinaison::create(['id_inscription' => $c->id_inscription, 'id_declinaison' => $m->id_declinaison, 'quantite' => 1, 'prix_unitaire' => 12]);
    InscriptionDeclinaison::create(['id_inscription' => $attente->id_inscription, 'id_declinaison' => $l->id_declinaison, 'quantite' => 1, 'prix_unitaire' => 0]);

    // Dons : un avec une inscription, un seul (sans inscription), un non payé.
    Don::create(['montant' => 15, 'id_commande' => $a->id_commande]);
    $seul = Commande::create(['date_commande' => now(), 'montant_total' => 30, 'statut' => Commande::PAYEE, 'id_user' => User::factory()->create()->id_user]);
    Don::create(['montant' => 30, 'id_commande' => $seul->id_commande]);
    Don::create(['montant' => 99, 'id_commande' => $attente->id_commande]);

    $stats = app(Statistiques::class);

    expect($stats->inscrits($this->evenement))->toMatchArray([
        Inscription::VALIDEE => 2,
        Inscription::DOSSIER_INCOMPLET => 1,
        Inscription::EN_ATTENTE_PAIEMENT => 1,
    ]);

    expect($stats->recettes($this->evenement))->toMatchArray([
        'inscriptions' => 68.0,
        'options' => 12.0,
        'dons' => 45.0,
        'total' => 125.0,
    ]);

    $tshirts = $stats->tshirts($this->evenement)->keyBy('taille');
    expect($tshirts['M']->vendus)->toBe(2)
        ->and($tshirts['L']->vendus)->toBe(0);

    $courses = $stats->courses($this->evenement)->keyBy(fn ($c) => $c->course->nom);
    expect($courses['Course 10 km']->reglees)->toBe(2)
        ->and($courses['Course 10 km']->dossards['attribues'])->toBe(2)
        ->and($courses['Course 10 km']->dossards['libres'])->toBe(998)
        ->and($courses['Course 5 km']->reglees)->toBe(1)
        ->and($courses['Course 5 km']->en_attente)->toBe(1);

    $this->actingAs(admin(1))->get('/admin')
        ->assertOk()
        ->assertSee('125 €')
        ->assertSee('dont 45 € de dons')
        ->assertDontSee('Frais Stripe');
});

test("une plage de dossards pleine déclenche une alerte", function () {
    $enfants = ($this->course)('Course enfants');
    $enfants->update(['dossard_debut' => 4000, 'dossard_fin' => 4000]);

    inscriptionPayee(User::factory()->create(), $enfants)->update(['dossard' => 4000]);
    inscriptionPayee(User::factory()->create(), $enfants); // validée, sans dossard

    $this->actingAs(admin(1))->get('/admin')
        ->assertSee('Dossards : Course enfants')
        ->assertSee('1 inscription(s) validée(s) sans dossard');
});

test("le filtre d'événement est retenu d'une page à l'autre", function () {
    $autre = Evenement::create(['nom' => 'Run For Lorène 4', 'date_evenement' => '2028-04-01', 'ouverture' => '2027-09-01', 'fermeture' => '2028-03-30', 'statut' => 'brouillon']);

    $this->actingAs(admin(1))->get('/admin?evenement='.$autre->id_evenement)->assertSee('Run For Lorène 4 · 1 avril 2028');
    $this->get('/admin')->assertSee('Run For Lorène 4 · 1 avril 2028');
});

test('le journal liste les actions admin', function () {
    $admin = admin(1);
    JournalAdmin::create(['id_admin' => $admin->id_user, 'action' => 'inscription.dossard', 'cible_type' => 'inscription', 'cible_id' => 42, 'details' => ['avant' => 1000, 'apres' => 1500]]);

    $this->actingAs($admin)->get('/admin/journal')
        ->assertOk()
        ->assertSee('Dossard modifié')
        ->assertSee($admin->name);
});
