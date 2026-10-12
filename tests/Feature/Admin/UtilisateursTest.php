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

    $this->put("/admin/utilisateurs/{$user->id_user}/niveau", ['niveau_acces' => 2])->assertSessionHas('statut');
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
