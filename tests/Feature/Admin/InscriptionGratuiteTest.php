<?php

use App\Models\Commande;
use App\Models\Consentement;
use App\Models\Course;
use App\Models\Inscription;
use App\Models\JournalAdmin;
use App\Models\User;
use App\Notifications\InscriptionOfferte;
use App\Services\Inscriptions\MettreAJourStatut;
use App\Services\Inscriptions\Questionnaires;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->seed();
    $this->course = fn (string $nom) => Course::where('nom', $nom)->first();
});

function gratuite(array $donnees = []): array
{
    return [
        'email' => 'parent@exemple.fr',
        'pour' => 'gere',
        'prenom' => 'Lina',
        'nom' => 'Petit',
        'date_naissance' => '2017-03-10',
        'titulaire_prenom' => 'Paul',
        'titulaire_nom' => 'Petit',
        'id_course' => Course::where('nom', 'Course enfants')->value('id_course'),
        'consentements' => '1',
        'motif' => 'Partenaire école',
        'prevenir' => '1',
        ...$donnees,
    ];
}

test("un admin crée une inscription gratuite pour l'enfant d'un nouveau compte", function () {
    $this->actingAs($admin = admin(2));
    $this->get('/admin/inscriptions/gratuite')->assertOk();

    $this->post('/admin/inscriptions/gratuite', gratuite())->assertRedirect()->assertSessionHasNoErrors();

    $parent = User::where('email', 'parent@exemple.fr')->sole();
    $inscription = Inscription::sole();

    expect($parent->mot_de_passe)->toBeNull()
        ->and($inscription->participant->id_responsable)->toBe($parent->id_user)
        ->and($inscription->participant->user->prenom)->toBe('Lina')
        ->and($inscription->prix_paye)->toBe('0.00')
        ->and($inscription->commande->statut)->toBe(Commande::GRATUITE)
        ->and((float) $inscription->commande->montant_total)->toBe(0.0)
        ->and($inscription->commande->id_user)->toBe($parent->id_user)
        ->and($inscription->commande->paiements()->exists())->toBeFalse()
        // Décharge et santé à remplir par la famille : pas de dossard pour l'instant.
        ->and($inscription->statut)->toBe(Inscription::DOSSIER_INCOMPLET)
        ->and($inscription->dossard)->toBeNull()
        ->and($inscription->typesConsentis())->toEqualCanonicalizing(['reglement', 'rgpd']);

    // Consentements recueillis par l'admin, à son nom ; rien sur le droit à l'image.
    expect(Consentement::whereHas('document', fn ($q) => $q->where('id_deposant', $admin->id_user))->count())->toBe(2);

    $trace = JournalAdmin::where('action', 'inscription.gratuite')->sole();
    expect($trace->details)->toMatchArray(['participant' => 'Lina Petit', 'motif' => 'Partenaire école']);

    Notification::assertSentTo($parent, InscriptionOfferte::class);
});

test("une fois la décharge et le questionnaire remplis, l'inscription gratuite est validée avec un dossard", function () {
    $this->actingAs(admin(2))->post('/admin/inscriptions/gratuite', gratuite());
    $inscription = Inscription::sole();
    $parent = $inscription->participant->id_responsable;

    app(Questionnaires::class)->remplirDecharge($inscription, affirmations(), $parent, '127.0.0.1');
    app(Questionnaires::class)->enregistrer($inscription, 'sante', reponses('sante'), true, $parent);
    app(MettreAJourStatut::class)($inscription->fresh());

    expect($inscription->fresh())
        ->statut->toBe(Inscription::VALIDEE)
        ->dossard->toBe(4000);
});

test("un compte existant inscrit lui-même : son profil est repris", function () {
    $adulte = User::factory()->create(['email' => 'camille@exemple.org']);

    $this->actingAs(admin(2))->post('/admin/inscriptions/gratuite', gratuite([
        'email' => 'CAMILLE@exemple.org',
        'pour' => 'titulaire',
        'prenom' => 'Autre',
        'nom' => 'Nom',
        'date_naissance' => '1990-01-01',
        'id_course' => ($this->course)('Course 10 km')->id_course,
        'prevenir' => '0',
    ]))->assertSessionHasNoErrors();

    expect(Inscription::sole()->id_participant)->toBe($adulte->id_user)
        ->and(User::count())->toBe(User::where('email', '!=', null)->count()); // aucun compte créé en plus
    Notification::assertNothingSent();
});

test("les règles du tunnel s'appliquent : âge, unicité, quota", function () {
    $this->actingAs(admin(2));

    // Âge : un enfant de 10 ans ne court pas le 10 km.
    $this->post('/admin/inscriptions/gratuite', gratuite(['id_course' => ($this->course)('Course 10 km')->id_course]))
        ->assertSessionHasErrors('id_course');

    // Unicité : pas deux inscriptions pour le même participant.
    $this->post('/admin/inscriptions/gratuite', gratuite())->assertSessionHasNoErrors();
    $this->post('/admin/inscriptions/gratuite', gratuite())->assertSessionHasErrors('email');

    // Quota : course complète.
    ($this->course)('Course enfants')->update(['quota' => 1]);
    $this->post('/admin/inscriptions/gratuite', gratuite(['prenom' => 'Noé']))->assertSessionHasErrors('id_course');

    expect(Inscription::count())->toBe(1);
});

test("sans consentements recueillis, pas d'inscription gratuite", function () {
    $this->actingAs(admin(2))->post('/admin/inscriptions/gratuite', gratuite(['consentements' => '']))
        ->assertSessionHasErrors('consentements');

    expect(Inscription::count())->toBe(0);
});

test("un nouveau compte qui inscrit un enfant doit avoir un nom", function () {
    $this->actingAs(admin(2))->post('/admin/inscriptions/gratuite', gratuite(['titulaire_prenom' => '', 'titulaire_nom' => '']))
        ->assertSessionHasErrors('titulaire_prenom');

    expect(User::where('email', 'parent@exemple.fr')->exists())->toBeFalse();
});

test("l'inscription gratuite demande le niveau 2", function () {
    $this->actingAs(admin(1));
    $this->get('/admin/inscriptions/gratuite')->assertForbidden();
    $this->post('/admin/inscriptions/gratuite', gratuite())->assertForbidden();
});
