<?php

use App\Models\Admin;
use App\Models\JournalAdmin;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->super = admin(3);
});

test('la recherche trouve un compte par le nom de son enfant', function () {
    $parent = User::factory()->create(['prenom' => 'Sam', 'nom' => 'Leroy']);
    $enfant = User::create(['prenom' => 'Zoé', 'nom' => 'Leroy']);
    Participant::create(['id_user' => $enfant->id_user, 'date_naissance' => '2015-01-01', 'id_responsable' => $parent->id_user]);

    $this->actingAs($this->super);
    $this->get('/admin/utilisateurs?q=Zoé')->assertOk()->assertSee('Sam Leroy');
    $this->get("/admin/utilisateurs/{$parent->id_user}")->assertOk()->assertSee('Zoé Leroy');
});

test('un super admin promeut un compte, change son niveau puis le retire', function () {
    $user = User::factory()->create();
    $this->actingAs($this->super);

    $this->put("/admin/utilisateurs/{$user->id_user}/niveau", ['niveau_acces' => 2, 'confirmation' => '1'])->assertSessionHas('statut');
    expect($user->fresh()->niveauAdmin())->toBe(2);

    $this->put("/admin/utilisateurs/{$user->id_user}/niveau", ['niveau_acces' => 0])->assertSessionHas('statut');
    expect($user->fresh()->niveauAdmin())->toBe(0);

    expect(JournalAdmin::where('cible_type', 'user')->pluck('action')->all())->toBe(['admin.niveau', 'admin.retrait']);
});

test("un admin ne change pas son propre niveau ; un autre super admin le peut", function () {
    $this->actingAs($this->super);
    $this->put("/admin/utilisateurs/{$this->super->id_user}/niveau", ['niveau_acces' => 1])->assertSessionHas('erreur');
    expect($this->super->fresh()->niveauAdmin())->toBe(3);

    $this->actingAs(admin(3))->put("/admin/utilisateurs/{$this->super->id_user}/niveau", ['niveau_acces' => 1])->assertSessionHas('statut');
    expect($this->super->fresh()->niveauAdmin())->toBe(1)
        ->and(Admin::where('niveau_acces', 3)->count())->toBe(1);
});

test("un compte à l'email non vérifié ne devient pas admin", function () {
    $user = User::factory()->create(['email_verifie_le' => null]);

    $this->actingAs($this->super)->put("/admin/utilisateurs/{$user->id_user}/niveau", ['niveau_acces' => 1])->assertSessionHas('erreur');
    expect($user->fresh()->niveauAdmin())->toBe(0);
});

test("réinitialiser l'accès efface le mot de passe et ferme les sessions", function () {
    $user = User::factory()->create();
    DB::table('sessions')->insert(['id' => 'sess1', 'user_id' => $user->id_user, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($this->super)->post("/admin/utilisateurs/{$user->id_user}/reinitialiser")->assertSessionHas('statut');

    expect($user->fresh()->mot_de_passe)->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $user->id_user)->exists())->toBeFalse()
        ->and(JournalAdmin::where('action', 'user.reinitialisation')->sole()->cible_id)->toBe($user->id_user);
});

test('la gestion des utilisateurs est réservée aux super admins', function () {
    $user = User::factory()->create();

    $this->actingAs(admin(2));
    $this->get('/admin/utilisateurs')->assertForbidden();
    $this->put("/admin/utilisateurs/{$user->id_user}/niveau", ['niveau_acces' => 3])->assertForbidden();
    $this->get('/admin')->assertDontSee('Utilisateurs');

    expect($user->fresh()->niveauAdmin())->toBe(0);
});

test('donner plus de droits demande une confirmation ; super admin, en recopiant l\'email', function () {
    Illuminate\Support\Facades\Notification::fake();
    $user = User::factory()->create(['email' => 'lea@exemple.fr']);
    $this->actingAs($this->super);

    // Sans la case cochée : refusé.
    $this->put("/admin/utilisateurs/{$user->id_user}/niveau", ['niveau_acces' => 1])->assertSessionHasErrorsIn('niveau', 'confirmation');
    expect($user->fresh()->niveauAdmin())->toBe(0);

    // Super admin : la case ne suffit pas, il faut recopier l'email exact.
    $this->put("/admin/utilisateurs/{$user->id_user}/niveau", ['niveau_acces' => 3, 'confirmation' => '1'])->assertSessionHasErrorsIn('niveau', 'confirmation_email');
    $this->put("/admin/utilisateurs/{$user->id_user}/niveau", ['niveau_acces' => 3, 'confirmation' => '1', 'confirmation_email' => 'autre@exemple.fr'])->assertSessionHasErrorsIn('niveau', 'confirmation_email');
    expect($user->fresh()->niveauAdmin())->toBe(0);

    $this->put("/admin/utilisateurs/{$user->id_user}/niveau", ['niveau_acces' => 3, 'confirmation' => '1', 'confirmation_email' => ' LEA@exemple.fr '])->assertSessionHas('statut');
    expect($user->fresh()->niveauAdmin())->toBe(3);

    // La personne est prévenue ; rétrograder ne demande pas de confirmation, mais prévient aussi.
    Illuminate\Support\Facades\Notification::assertSentTo($user, App\Notifications\AccesAdminModifie::class, fn ($n) => $n->apres === 3);
    $this->put("/admin/utilisateurs/{$user->id_user}/niveau", ['niveau_acces' => 1])->assertSessionHas('statut');
    Illuminate\Support\Facades\Notification::assertSentToTimes($user, App\Notifications\AccesAdminModifie::class, 2);
});

test('un compte qui n\'a jamais servi se supprime, avec ses enfants et ses brouillons', function () {
    $this->seed();
    $parent = User::factory()->create(['prenom' => 'Sam', 'nom' => 'Leroy']);
    $enfant = User::create(['prenom' => 'Zoé', 'nom' => 'Leroy']);
    Participant::create(['id_user' => $enfant->id_user, 'date_naissance' => '2015-01-01', 'id_responsable' => $parent->id_user]);
    Participant::create(['id_user' => $parent->id_user, 'date_naissance' => '1985-01-01']);
    $brouillon = App\Models\Inscription::create([
        'id_participant' => $enfant->id_user, 'id_tarif' => App\Models\Course::first()->tarifs()->first()->id_tarif,
        'id_evenement' => App\Models\Evenement::first()->id_evenement, 'statut' => App\Models\Inscription::BROUILLON,
    ]);

    $this->actingAs($this->super)->get("/admin/utilisateurs/{$parent->id_user}")->assertSee('Supprimer le compte')->assertSee('participants gérés (Zoé)');
    $this->delete("/admin/utilisateurs/{$parent->id_user}")->assertRedirect('/admin/utilisateurs');

    expect(User::find($parent->id_user))->toBeNull()
        ->and(User::find($enfant->id_user))->toBeNull()
        ->and(App\Models\Inscription::find($brouillon->id_inscription))->toBeNull()
        ->and(JournalAdmin::where('action', 'user.suppression')->sole()->details)->toMatchArray(['nom' => 'Sam Leroy', 'participants gérés supprimés' => 1]);
});

test("un compte qui a servi (inscription, admin, soi-même) ne se supprime pas", function () {
    $this->seed();
    $inscrit = User::factory()->create();
    inscriptionPayee($inscrit, App\Models\Course::first());
    $admin = admin(1);

    $this->actingAs($this->super);
    $this->get("/admin/utilisateurs/{$inscrit->id_user}")->assertSee('Impossible pour ce compte')->assertSee('passé des commandes');
    $this->delete("/admin/utilisateurs/{$inscrit->id_user}")->assertSessionHas('erreur');
    $this->delete("/admin/utilisateurs/{$admin->id_user}")->assertSessionHas('erreur');
    $this->delete("/admin/utilisateurs/{$this->super->id_user}")->assertSessionHas('erreur');

    $this->actingAs(admin(2))->delete("/admin/utilisateurs/{$inscrit->id_user}")->assertForbidden();

    expect(User::find($inscrit->id_user))->not->toBeNull()
        ->and(User::find($admin->id_user))->not->toBeNull();
});
