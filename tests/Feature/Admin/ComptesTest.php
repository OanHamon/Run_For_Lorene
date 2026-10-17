<?php

use App\Models\JournalAdmin;
use App\Models\User;
use App\Notifications\CompteCree;
use App\Support\Activation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

/*
 * Comptes créés par l'association, séparés des inscriptions : un compte comme ceux du site,
 * activé par la personne via un lien « Créer mon mot de passe » (signé, 7 jours, usage unique).
 */

beforeEach(function () {
    Notification::fake();
});

/** Lien d'activation tel qu'envoyé dans l'email. */
function lienActivation(User $user, ?string $suite = null): string
{
    $lien = null;
    Notification::assertSentTo($user, CompteCree::class, function (CompteCree $n) use ($user, &$lien) {
        $lien = $n->toMail($user)->actionUrl;

        return true;
    });

    return $lien;
}

function creerCompte(array $d = []): User
{
    test()->post('/admin/utilisateurs', ['prenom' => 'Marie', 'nom' => 'Dupont', 'email' => ' Marie.Dupont@Exemple.fr ', 'telephone' => '06 12 34 56 78', ...$d])
        ->assertSessionHasNoErrors();

    return User::where('email', 'marie.dupont@exemple.fr')->sole();
}

test("un admin de niveau 2 crée un compte : sans mot de passe, et la personne reçoit son lien", function () {
    $this->actingAs($admin = admin(2));
    $this->get('/admin/utilisateurs/nouveau?email=marie.dupont@exemple.fr')->assertOk()->assertSee('value="marie.dupont@exemple.fr"', false);

    $marie = creerCompte();

    expect($marie->mot_de_passe)->toBeNull()
        ->and($marie->hasVerifiedEmail())->toBeFalse()
        ->and($marie->telephone)->toBe('0612345678')
        ->and(JournalAdmin::where('action', 'user.creation')->sole()->id_admin)->toBe($admin->id_user);

    Notification::assertSentTo($marie, CompteCree::class, function (CompteCree $n) use ($marie) {
        $mail = $n->toMail($marie);

        return $mail->subject === 'Ton compte Run For Lorène est prêt' && $mail->actionText === 'Créer mon mot de passe';
    });
});

test("un compte qui existe déjà n'est pas recréé ; le niveau 1 ne crée pas de compte", function () {
    User::factory()->create(['email' => 'marie.dupont@exemple.fr']);

    $this->actingAs(admin(2))->post('/admin/utilisateurs', ['prenom' => 'M', 'nom' => 'D', 'email' => 'marie.dupont@exemple.fr'])
        ->assertSessionHasErrors(['email' => 'Un compte existe déjà avec cette adresse.']);

    $this->actingAs(admin(1))->get('/admin/utilisateurs/nouveau')->assertForbidden();
});

test("depuis l'inscription gratuite, on revient à l'inscription après la création du compte", function () {
    $this->actingAs(admin(2))
        ->post('/admin/utilisateurs', ['prenom' => 'Marie', 'nom' => 'Dupont', 'email' => 'marie.dupont@exemple.fr', 'retour' => 'inscription'])
        ->assertRedirect(route('admin.inscriptions.create', ['email' => 'marie.dupont@exemple.fr']));
});

test("le lien d'activation fait choisir le mot de passe, connecte et mène à la page prévue", function () {
    $this->actingAs(admin(2));
    $marie = creerCompte();
    Auth::logout();

    $lien = Activation::url($marie, '/espace');
    $this->get($lien)->assertOk()->assertSee('Bienvenue Marie !')->assertSee('marie.dupont@exemple.fr');

    // Mots de passe trop court, puis différents : refusés.
    $this->post($lien, ['mot_de_passe' => 'court', 'mot_de_passe_confirmation' => 'court'])->assertSessionHasErrors('mot_de_passe');
    $this->post($lien, ['mot_de_passe' => 'un-bon-mot', 'mot_de_passe_confirmation' => 'autre-chose'])->assertSessionHasErrors('mot_de_passe');

    $this->post($lien, ['mot_de_passe' => 'un-bon-mot', 'mot_de_passe_confirmation' => 'un-bon-mot'])->assertRedirect('/espace');

    $marie = $marie->fresh();
    expect(Hash::check('un-bon-mot', $marie->mot_de_passe))->toBeTrue()
        ->and($marie->hasVerifiedEmail())->toBeTrue()
        ->and(Auth::id())->toBe($marie->id_user);
});

test("le lien ne sert qu'une fois", function () {
    $this->actingAs(admin(2));
    $marie = creerCompte();
    Auth::logout();
    $lien = lienActivation($marie);

    $this->post($lien, ['mot_de_passe' => 'un-bon-mot', 'mot_de_passe_confirmation' => 'un-bon-mot'])->assertRedirect();
    Auth::logout();

    $this->get($lien)->assertOk()->assertSee('Ton compte est déjà prêt');
    $this->post($lien, ['mot_de_passe' => 'pirate123', 'mot_de_passe_confirmation' => 'pirate123'])->assertSee('déjà prêt');
    expect(Hash::check('un-bon-mot', $marie->fresh()->mot_de_passe))->toBeTrue();
});

test("un lien expiré, modifié, ou parti vers une adresse corrigée n'est plus valable", function () {
    $this->actingAs(admin(3));
    $marie = creerCompte();
    $lien = Activation::url($marie);

    // Modifié à la main : signature invalide.
    $this->get(str_replace('cle=', 'cle=x', $lien))->assertSee("Ce lien n'est plus valable", false);

    // Expiré (7 jours).
    $this->travel(Activation::VALIDITE_JOURS + 1)->days();
    $this->get($lien)->assertSee("Ce lien n'est plus valable", false);
    $this->travelBack();

    // Email corrigé : l'ancien lien meurt, un nouveau part.
    $this->put("/admin/utilisateurs/{$marie->id_user}/email", ['email' => 'marie.dupont2@exemple.fr'])->assertSessionHasNoErrors();
    Auth::logout();
    $this->get($lien)->assertSee("Ce lien n'est plus valable", false);
    $this->get(Activation::url($marie->fresh()))->assertSee('Bienvenue Marie !');
});

test("la destination après activation reste dans l'espace (pas de redirection ouverte)", function () {
    expect(Activation::suite('/espace/inscriptions/12'))->toBe('/espace/inscriptions/12')
        ->and(Activation::suite('https://pirate.example'))->toBe('/espace')
        ->and(Activation::suite('//pirate.example/espace'))->toBe('/espace')
        ->and(Activation::suite('/admin'))->toBe('/espace');
});

test("renvoyer le lien d'un compte pas encore activé, pas plus d'une fois par 10 minutes", function () {
    $this->actingAs(admin(2));
    $marie = creerCompte();

    $this->post("/admin/utilisateurs/{$marie->id_user}/activation")->assertSessionHas('statut');
    $this->post("/admin/utilisateurs/{$marie->id_user}/activation")->assertSessionHas('erreur');

    Notification::assertSentToTimes($marie, CompteCree::class, 2); // création + un renvoi
});

test("réinitialiser l'accès envoie un lien pour choisir un nouveau mot de passe", function () {
    $user = User::factory()->create();

    $this->actingAs(admin(3))->post("/admin/utilisateurs/{$user->id_user}/reinitialiser")->assertSessionHas('statut');

    expect($user->fresh()->mot_de_passe)->toBeNull();
    Notification::assertSentTo($user, CompteCree::class, fn (CompteCree $n) => $n->reinitialisation
        && $n->toMail($user->fresh())->actionText === 'Choisir un nouveau mot de passe');
});
