<?php

use App\Models\Commande;
use App\Models\Consentement;
use App\Models\Course;
use App\Models\Inscription;
use App\Models\JournalAdmin;
use App\Models\Participant;
use App\Models\User;
use App\Notifications\InscriptionOfferte;
use App\Services\Inscriptions\MettreAJourStatut;
use App\Services\Inscriptions\Questionnaires;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;

/*
 * Inscription manuelle gratuite, en deux temps : l'email du compte, puis le choix explicite
 * du participant (titulaire, participant existant, nouvelle personne).
 */

beforeEach(function () {
    Notification::fake();
    $this->seed();
    $this->course = fn (string $nom) => Course::where('nom', $nom)->first();

    // Compte créé par l'association, pas encore activé (pas de mot de passe).
    $this->parent = User::create(['prenom' => 'Paul', 'nom' => 'Petit', 'email' => 'parent@exemple.fr']);
    $this->parent->forceFill(['remember_token' => Illuminate\Support\Str::random(60)])->save();
});

/** Nouvelle personne (Lina, 10 ans) rattachée au compte parent@exemple.fr. */
function gratuite(array $donnees = []): array
{
    return [
        'email' => 'parent@exemple.fr',
        'participant' => 'nouveau',
        'prenom' => 'Lina',
        'nom' => 'Petit',
        'date_naissance' => '2017-03-10',
        'id_course' => Course::where('nom', 'Course enfants')->value('id_course'),
        'consentements' => '1',
        'motif' => 'Partenaire école',
        'prevenir' => '1',
        ...$donnees,
    ];
}

/** Compte existant (Marie Dupont) avec un enfant déjà rattaché (Léa, 9 ans). */
function familleDupont(): array
{
    $marie = User::factory()->create(['prenom' => 'Marie', 'nom' => 'Dupont', 'email' => 'marie.dupont@exemple.fr']);
    $lea = User::create(['prenom' => 'Léa', 'nom' => 'Dupont']);
    $participant = Participant::create(['id_user' => $lea->id_user, 'date_naissance' => '2018-05-02', 'id_responsable' => $marie->id_user]);

    return [$marie, $participant];
}

test("étape 1 : l'email retrouve le compte et ses participants, ou prévient qu'il n'existe pas", function () {
    [$marie] = familleDupont();
    $this->actingAs(admin(2));

    $this->get('/admin/inscriptions/gratuite')->assertOk()->assertSee('1. Le compte de la famille')->assertDontSee('2. Qui participe ?');

    $this->get('/admin/inscriptions/gratuite?email=Marie.Dupont@exemple.fr')
        ->assertSee('Compte trouvé : <strong>Marie Dupont</strong>', false)
        ->assertSee('Léa Dupont')
        ->assertSee('né(e) le 02/05/2018');

    // Pas de compte : l'inscription n'en crée pas, elle renvoie vers la création de compte.
    $this->get('/admin/inscriptions/gratuite?email=marie.dupond@exemple.fr')
        ->assertSee('Aucun compte avec marie.dupond@exemple.fr')
        ->assertSee("Vérifie d'abord l'orthographe", false)
        ->assertSee(route('admin.utilisateurs.create', ['email' => 'marie.dupond@exemple.fr', 'retour' => 'inscription']))
        ->assertDontSee('2. Qui participe ?');
});

test("nouvelle personne sur un compte pas encore activé : commande gratuite, invitation avec lien de mot de passe", function () {
    $this->actingAs($admin = admin(2));
    $this->post('/admin/inscriptions/gratuite', gratuite())->assertRedirect()->assertSessionHasNoErrors();

    $parent = User::where('email', 'parent@exemple.fr')->sole();
    $inscription = Inscription::sole();

    expect($parent->mot_de_passe)->toBeNull()
        ->and($parent->hasVerifiedEmail())->toBeFalse()
        ->and($inscription->participant->id_responsable)->toBe($parent->id_user)
        ->and($inscription->participant->user->prenom)->toBe('Lina')
        ->and($inscription->commande->statut)->toBe(Commande::GRATUITE)
        ->and((float) $inscription->commande->montant_total)->toBe(0.0)
        ->and($inscription->commande->id_user)->toBe($parent->id_user)
        ->and($inscription->statut)->toBe(Inscription::DOSSIER_INCOMPLET)
        ->and($inscription->typesConsentis())->toEqualCanonicalizing(['reglement', 'rgpd']);

    expect(Consentement::whereHas('document', fn ($q) => $q->where('id_deposant', $admin->id_user))->count())->toBe(2);
    expect(JournalAdmin::where('action', 'inscription.gratuite')->sole()->details)->toMatchArray(['participant' => 'Lina Petit', 'titulaire' => 'parent@exemple.fr', 'motif' => 'Partenaire école']);

    // Compte pas encore activé : le bouton fait créer le mot de passe, puis mène à l'inscription.
    Notification::assertSentTo($parent, InscriptionOfferte::class, function ($n) use ($parent, $inscription) {
        $mail = $n->toMail($parent);

        return $mail->subject === 'Lina est inscrit(e) à Run For Lorène 3'
            && $mail->actionText === 'Créer mon mot de passe'
            && str_contains($mail->actionUrl, '/activation/'.$parent->id_user)
            && str_contains(urldecode($mail->actionUrl), 'suite=/espace/inscriptions/'.$inscription->id_inscription);
    });
});

test("compte déjà activé : l'invitation mène directement à l'inscription", function () {
    $this->parent->forceFill(['mot_de_passe' => Hash::make('secret123'), 'email_verifie_le' => now()])->save();

    $this->actingAs(admin(2))->post('/admin/inscriptions/gratuite', gratuite())->assertSessionHasNoErrors();
    $inscription = Inscription::sole();

    Notification::assertSentTo($this->parent, InscriptionOfferte::class, function ($n) use ($inscription) {
        $mail = $n->toMail($this->parent);

        return $mail->actionText === 'Remplir les documents' && $mail->actionUrl === route('espace.inscription', $inscription);
    });
});

test("une fois la décharge et le questionnaire remplis, l'inscription gratuite est validée avec un dossard", function () {
    $this->actingAs(admin(2))->post('/admin/inscriptions/gratuite', gratuite());
    $inscription = Inscription::sole();
    $parent = $inscription->participant->id_responsable;

    app(Questionnaires::class)->remplirDecharge($inscription, affirmations(), $parent, '127.0.0.1');
    app(Questionnaires::class)->enregistrer($inscription, 'sante', reponses('sante'), true, $parent);
    app(MettreAJourStatut::class)($inscription->fresh());

    expect($inscription->fresh())->statut->toBe(Inscription::VALIDEE)->dossard->toBe(4000);
});

test('un participant déjà rattaché au compte se choisit dans la liste, sans doublon', function () {
    [$marie, $lea] = familleDupont();
    $this->actingAs(admin(2));

    $this->post('/admin/inscriptions/gratuite', gratuite(['email' => $marie->email, 'participant' => (string) $lea->id_user]))->assertSessionHasNoErrors();

    expect(Inscription::sole()->id_participant)->toBe($lea->id_user)
        ->and($marie->participantsGeres()->count())->toBe(1);
});

test("une « nouvelle personne » qui ressemble à un participant existant est refusée", function () {
    [$marie] = familleDupont();

    // « Lea » sans accent, même date de naissance que Léa : c'est elle.
    $this->actingAs(admin(2))
        ->post('/admin/inscriptions/gratuite', gratuite(['email' => $marie->email, 'prenom' => 'Lea', 'nom' => 'Dupont', 'date_naissance' => '2018-05-02']))
        ->assertSessionHasErrors(['participant']);

    expect(session('errors')->first('participant'))->toContain('Léa Dupont, né(e) le 02/05/2018, est déjà rattaché(e) à ce compte')
        ->and($marie->participantsGeres()->count())->toBe(1)
        ->and(Inscription::count())->toBe(0);
});

test("on ne peut pas inscrire le participant d'une autre famille", function () {
    [, $lea] = familleDupont();
    $autre = User::factory()->create(['email' => 'autre@exemple.fr']);

    $this->actingAs(admin(2))
        ->post('/admin/inscriptions/gratuite', gratuite(['email' => $autre->email, 'participant' => (string) $lea->id_user]))
        ->assertSessionHasErrors('participant');

    expect(Inscription::count())->toBe(0);
});

test("un compte existant inscrit lui-même : sa date de naissance est demandée s'il n'en a pas", function () {
    $adulte = User::factory()->create(['email' => 'camille@exemple.org']);
    $champs = gratuite(['email' => 'CAMILLE@exemple.org', 'participant' => 'titulaire', 'id_course' => ($this->course)('Course 10 km')->id_course, 'prevenir' => '0']);

    $this->actingAs(admin(2))->post('/admin/inscriptions/gratuite', $champs)->assertSessionHasErrors('date_naissance_titulaire');
    $this->post('/admin/inscriptions/gratuite', [...$champs, 'date_naissance_titulaire' => '1990-01-01'])->assertSessionHasNoErrors();

    expect(Inscription::sole()->id_participant)->toBe($adulte->id_user)
        ->and($adulte->participant->fresh()->date_naissance->toDateString())->toBe('1990-01-01');
    Notification::assertNothingSent();
});

test("les règles du tunnel s'appliquent : âge, unicité, quota", function () {
    $this->actingAs(admin(2));

    $this->post('/admin/inscriptions/gratuite', gratuite(['id_course' => ($this->course)('Course 10 km')->id_course]))->assertSessionHasErrors('id_course');

    $this->post('/admin/inscriptions/gratuite', gratuite())->assertSessionHasNoErrors();
    $lina = Inscription::sole()->id_participant;
    $this->post('/admin/inscriptions/gratuite', gratuite(['participant' => (string) $lina]))->assertSessionHasErrors('participant');

    ($this->course)('Course enfants')->update(['quota' => 1]);
    $this->post('/admin/inscriptions/gratuite', gratuite(['prenom' => 'Noé', 'date_naissance' => '2016-01-01']))->assertSessionHasErrors('id_course');

    expect(Inscription::count())->toBe(1);
});

test("sans consentements recueillis, ou sans compte existant, pas d'inscription ; jamais de compte créé au passage", function () {
    $this->actingAs(admin(2));
    $this->post('/admin/inscriptions/gratuite', gratuite(['consentements' => '']))->assertSessionHasErrors('consentements');
    $this->post('/admin/inscriptions/gratuite', gratuite(['email' => 'inconnu@exemple.fr']))->assertSessionHasErrors('email');

    expect(Inscription::count())->toBe(0)
        ->and(User::where('email', 'inconnu@exemple.fr')->exists())->toBeFalse();
});

test("l'inscription gratuite demande le niveau 2", function () {
    $this->actingAs(admin(1));
    $this->get('/admin/inscriptions/gratuite')->assertForbidden();
    $this->post('/admin/inscriptions/gratuite', gratuite())->assertForbidden();
});

test("le super admin corrige l'email d'un compte qui ne s'est jamais connecté, pas celui d'un compte utilisé", function () {
    $parent = $this->parent;
    $parent->forceFill(['email' => 'paul.petti@exemple.fr'])->save();
    $this->actingAs(admin(2))->post('/admin/inscriptions/gratuite', gratuite(['email' => 'paul.petti@exemple.fr']));

    $this->actingAs(admin(3));
    $this->get('/admin/inscriptions/'.Inscription::sole()->id_inscription)->assertSee('pas encore activé');
    $this->get("/admin/utilisateurs/{$parent->id_user}")->assertSee("Corriger l'email", false);
    $this->put("/admin/utilisateurs/{$parent->id_user}/email", ['email' => ' Paul.Petit@exemple.fr '])->assertSessionHasNoErrors();

    expect($parent->fresh()->email)->toBe('paul.petit@exemple.fr')
        ->and(JournalAdmin::where('action', 'user.email')->sole()->details)->toBe(['avant' => 'paul.petti@exemple.fr', 'après' => 'paul.petit@exemple.fr']);
    // Un nouveau lien part vers la bonne adresse.
    Notification::assertSentTo($parent->fresh(), App\Notifications\CompteCree::class);

    // Une fois connecté (email vérifié), l'email ne se change plus ici.
    $parent->markEmailAsVerified();
    $this->put("/admin/utilisateurs/{$parent->id_user}/email", ['email' => 'autre@exemple.fr'])->assertSessionHas('erreur');
    expect($parent->fresh()->email)->toBe('paul.petit@exemple.fr');
});

test("« Mon compte » : modifier ses informations et choisir un mot de passe facultatif", function () {
    $user = User::factory()->create(['mot_de_passe' => null, 'telephone' => null]);
    $this->actingAs($user);

    $this->get('/espace/compte')->assertOk()->assertSee('Choisir un mot de passe (facultatif)');
    $this->get('/espace')->assertSee(route('espace.compte'));

    Volt::test('espace.compte')
        ->set('telephone', '06 11 22 33 44')
        ->call('enregistrerInfos')
        ->assertHasNoErrors()
        ->set('nouveau', 'courte')
        ->set('nouveau_confirmation', 'courte')
        ->call('enregistrerMotDePasse')
        ->assertHasErrors('nouveau')
        ->set('nouveau', 'un-bon-mot-de-passe')
        ->set('nouveau_confirmation', 'un-bon-mot-de-passe')
        ->call('enregistrerMotDePasse')
        ->assertHasNoErrors();

    expect($user->fresh()->telephone)->toBe('0611223344')
        ->and(Hash::check('un-bon-mot-de-passe', $user->fresh()->mot_de_passe))->toBeTrue();
});
