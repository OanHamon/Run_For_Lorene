<?php

use App\Models\Course;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\Participant;
use App\Models\Remboursement;
use App\Models\User;

beforeEach(function () {
    $this->seed();
    $this->evenement = Evenement::first();
    $this->course = Course::where('id_evenement', $this->evenement->id_evenement)->first();
});

test('la barre latérale est découpée en sections, selon le niveau d\'accès', function () {
    $this->actingAs(admin(3))->get('/admin')->assertOk()
        ->assertSeeInOrder(['Pilotage', 'Tableau de bord', 'À traiter', 'Participants', 'Inscriptions', 'Questionnaires de santé', 'Comptes', 'Événement', 'Courses et tarifs', 'Textes et documents', 'Finances', 'Commandes et paiements', 'Remboursements', 'Dons et reçus', 'Administration', 'Admins et accès', 'Journal']);

    $this->actingAs(admin(1))->get('/admin')->assertOk()
        ->assertDontSee('Questionnaires de santé')
        ->assertDontSee('Admins et accès')
        ->assertSee('Remboursements');
});

test("le fil d'Ariane situe la page dans sa section", function () {
    $inscription = inscriptionPayee(User::factory()->create(), $this->course);

    $this->actingAs(admin(2))->get(route('admin.inscriptions.show', $inscription))->assertOk()
        ->assertSee("Fil d'Ariane", false)
        ->assertSeeInOrder(['Participants', '›', 'Inscriptions']);
});

test("l'onglet textes de l'événement relève de « Textes et documents »", function () {
    $this->actingAs(admin(1))->get(route('admin.evenements.show', [$this->evenement, 'onglet' => 'textes']))
        ->assertOk()
        ->assertSeeInOrder(['Événement', '›', 'Textes et documents']);
});

test("le sélecteur de la barre change l'événement suivi partout et revient sur la page", function () {
    $autre = Evenement::create(['nom' => 'Run For Lorène 4', 'date_evenement' => '2028-04-01', 'ouverture' => '2027-10-01', 'fermeture' => '2028-03-25', 'statut' => Evenement::BROUILLON]);
    $this->actingAs(admin(1));

    $this->from('/admin/inscriptions?statut=validee&evenement='.$this->evenement->id_evenement)
        ->post('/admin/evenement-suivi', ['evenement' => $autre->id_evenement])
        ->assertRedirect(url('/admin/inscriptions?statut=validee'));

    $this->get('/admin/dons')->assertSee('Run For Lorène 4');

    // Pas de redirection vers un autre site.
    $this->from('https://exemple.com/')->post('/admin/evenement-suivi', ['evenement' => $autre->id_evenement])->assertRedirect(route('admin.tableau'));
});

test('« À traiter » liste les dossiers incomplets, avec un compteur dans la barre', function () {
    $user = User::factory()->create(['prenom' => 'Inès', 'nom' => 'Garnier']);
    $inscription = inscriptionPayee($user, $this->course, Inscription::DOSSIER_INCOMPLET);

    $this->actingAs(admin(1))->get('/admin/a-traiter')->assertOk()
        ->assertSee('Dossiers incomplets')
        ->assertSee('Inès Garnier')
        ->assertSee('la décharge de responsabilité')
        ->assertSee(route('admin.inscriptions.show', $inscription));

    $this->get('/admin')->assertSee('1 à traiter', false);
});

test('sans rien en attente, « À traiter » le dit', function () {
    $this->actingAs(admin(1))->get('/admin/a-traiter')->assertOk()->assertSee('Rien à traiter');
});

test('les questionnaires de santé à vérifier ne sont visibles qu\'à partir du niveau 2', function () {
    $enfant = User::factory()->create();
    Participant::create(['id_user' => $enfant->id_user, 'date_naissance' => now()->subYears(12)->toDateString()]);
    $inscription = inscriptionPayee($enfant, $this->course);
    $inscription->participant->update(['date_naissance' => now()->subYears(12)->toDateString()]);
    documentsComplets($inscription->fresh());

    $this->actingAs(admin(2))->get('/admin/a-traiter')->assertSee('Questionnaires de santé à vérifier');
    $this->actingAs(admin(1))->get('/admin/a-traiter')->assertDontSee('Questionnaires de santé à vérifier');
});

test('la recherche trouve une inscription par nom ou par dossard, et une commande par numéro', function () {
    $user = User::factory()->create(['prenom' => 'Basile', 'nom' => 'Renaud']);
    $inscription = inscriptionPayee($user, $this->course);
    $inscription->update(['dossard' => 1042]);

    $this->actingAs(admin(1));
    $this->get('/admin/recherche?q=basile')->assertOk()->assertSee('Basile Renaud');
    $this->get('/admin/recherche?q=1042')->assertOk()->assertSee('Basile Renaud');
    $this->get('/admin/recherche?q='.$inscription->id_commande)->assertSee('Commande n° '.$inscription->id_commande);
    $this->get('/admin/recherche?q=zzzz')->assertSee('Aucun résultat');

    // Les comptes ne s'affichent qu'au super admin.
    $this->get('/admin/recherche?q=basile')->assertDontSee('Comptes</h2>', false);
    $this->actingAs(admin(3))->get('/admin/recherche?q=basile')->assertSee('Comptes</h2>', false);
});

test('« Comptes » ne liste que les participants, « Admins et accès » les admins', function () {
    $super = admin(3);
    $participant = User::factory()->create(['prenom' => 'Paul', 'nom' => 'Participant']);

    $this->actingAs($super)->get('/admin/utilisateurs')->assertOk()->assertSee('Paul Participant')->assertDontSee($super->email);
    $this->get('/admin/admins')->assertOk()->assertSee($super->email)->assertDontSee('Paul Participant')
        ->assertSee(route('admin.utilisateurs.create', ['niveau' => 1]));

    $this->actingAs(admin(2))->get('/admin/admins')->assertForbidden();
});

test('la page Remboursements liste ceux de l\'événement suivi', function () {
    $inscription = inscriptionPayee(User::factory()->create(['prenom' => 'Rémi', 'nom' => 'Dupuis']), $this->course);
    $paiement = Paiement::create(['prestataire' => 'stripe', 'reference' => 'pi_test_1', 'montant' => 10, 'statut' => 'reussi', 'date_paiement' => now(), 'id_commande' => $inscription->id_commande]);
    Remboursement::create(['reference' => 're_test_1', 'montant' => 10, 'motif' => 'Blessure', 'date_remboursement' => now(), 'id_paiement' => $paiement->id_paiement]);

    $this->actingAs(admin(1))->get('/admin/remboursements')->assertOk()
        ->assertSee('Rémi Dupuis')->assertSee('Blessure')->assertSee('Stripe');
});

test('la barre est un accordéon : seule la section de la page courante est ouverte', function () {
    inscriptionPayee(User::factory()->create(), $this->course, Inscription::DOSSIER_INCOMPLET);

    $html = $this->actingAs(admin(3))->get('/admin/remboursements')->assertOk()->getContent();

    expect(substr_count($html, '<details name="nav-admin"'))->toBe(5)
        ->and(preg_match_all('/name="nav-admin" class="group"\s+open\s*>/', $html))->toBe(1);
    // La section ouverte est « Finances » ; « Pilotage », fermée, garde le compteur de « À traiter ».
    expect($html)->toMatch('/<details name="nav-admin" class="group"\s+open\s*>\s*<summary[^>]*>\s*<span>Finances/')
        ->toMatch('/<span>Pilotage<\/span>\s*<span class="flex items-center gap-2">\s*<span class="rounded-full bg-moutarde[^>]*>1<\/span>/');
});

test('sans page reconnue, la première section est ouverte', function () {
    $html = $this->actingAs(admin(1))->get('/admin/recherche?q=x')->getContent();

    expect($html)->toMatch('/<details name="nav-admin" class="group"\s+open\s*>\s*<summary[^>]*>\s*<span>Pilotage/');
});
