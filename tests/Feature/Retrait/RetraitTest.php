<?php

use App\Models\Course;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\Participant;
use App\Models\PosteRetrait;
use App\Models\RemiseDossard;
use App\Models\User;
use App\Notifications\InscriptionValidee;
use App\Support\Billet;

beforeEach(function () {
    $this->seed();
    $this->evenement = Evenement::first();
    $this->course = Course::where('id_evenement', $this->evenement->id_evenement)->first();
    $this->inscription = inscriptionPayee(User::factory()->create(['prenom' => 'Jade', 'nom' => 'Rivière']), $this->course);
    $this->inscription->update(['dossard' => 1042]);
});

/** Ouvre un poste de retrait valable aujourd'hui et renvoie son jeton. */
function posteOuvert(Evenement $evenement, array $attributs = []): string
{
    [, $jeton] = PosteRetrait::ouvrir($attributs + ['libelle' => 'Entrée', 'valable_du' => today(), 'valable_au' => today(), 'id_evenement' => $evenement->id_evenement, 'id_admin' => admin(2, confirme: false)->id_user]);

    return $jeton;
}

test('le jeton du billet est signé : impossible de le fabriquer pour une autre inscription', function () {
    $jeton = Billet::jeton($this->inscription);

    expect($jeton)->toStartWith('RFL-'.$this->inscription->id_inscription.'-')
        ->and(Billet::lire($jeton)?->id_inscription)->toBe($this->inscription->id_inscription)
        ->and(Billet::lire('RFL-'.($this->inscription->id_inscription + 1).'-'.substr($jeton, -20)))->toBeNull()
        ->and(Billet::lire('n\'importe quoi'))->toBeNull();
});

test("le billet s'ouvre par un lien signé, seulement pour une inscription validée", function () {
    $this->get(Billet::url($this->inscription))->assertOk()->assertSee('Jade Rivière')->assertSee('1042')->assertSee('<svg', false)
        ->assertSee('data-code="'.Billet::jeton($this->inscription).'"', false)
        ->assertSee('Le scan ne passe pas');
    $this->get(route('billet', $this->inscription))->assertForbidden();

    $this->inscription->update(['statut' => Inscription::DOSSIER_INCOMPLET]);
    $this->get(Billet::url($this->inscription))->assertOk()->assertSee('Billet indisponible')->assertDontSee('QR code du billet')->assertDontSee('data-code', false);
});

test("l'email « inscription validée » et l'espace donnent le billet", function () {
    $user = $this->inscription->participant->user;
    $mail = (new InscriptionValidee($this->inscription))->toMail($user);
    expect(implode(' ', $mail->introLines))->toContain(Billet::url($this->inscription));

    $this->actingAs($user)->get(route('espace.inscription', $this->inscription))->assertOk()->assertSee('Ton billet')->assertSee('<svg', false)
        ->assertSee('data-code="'.Billet::jeton($this->inscription).'"', false)
        ->assertSee('wire:ignore', false)
        ->assertSeeInOrder(['Ton billet', 'Dossard n° </span>1042'], false);
});

test("l'admin remet un dossard par le billet scanné, une seule fois", function () {
    $this->actingAs($admin = admin(2));

    $this->post('/admin/retrait/scan', ['jeton' => Billet::jeton($this->inscription)])->assertRedirect('/admin/retrait');
    $this->get('/admin/retrait')->assertOk()->assertSee('Jade Rivière')->assertSee('Remettre le dossard');

    $this->post("/admin/retrait/{$this->inscription->id_inscription}/remettre")->assertSessionHas('retrait.succes');
    expect($this->inscription->fresh()->date_retrait)->not->toBeNull()
        ->and(RemiseDossard::sole()->par)->toBe($admin->name);

    $this->post("/admin/retrait/{$this->inscription->id_inscription}/remettre")->assertSessionHas('retrait.erreur', fn ($m) => str_contains($m, 'déjà remis'));
    expect(RemiseDossard::count())->toBe(1);

    $this->post("/admin/retrait/{$this->inscription->id_inscription}/annuler")->assertSessionHas('retrait.succes');
    expect($this->inscription->fresh()->date_retrait)->toBeNull();
});

test('un faux billet, ou un dossier incomplet, ne donne pas de dossard', function () {
    $this->actingAs(admin(2));
    $this->post('/admin/retrait/scan', ['jeton' => 'RFL-1-00000000000000000000'])->assertSessionHas('retrait.erreur');

    $incomplet = inscriptionPayee(User::factory()->create(), $this->course, Inscription::DOSSIER_INCOMPLET);
    $this->post("/admin/retrait/{$incomplet->id_inscription}/remettre")->assertSessionHas('retrait.erreur', fn ($m) => str_contains($m, 'Dossier incomplet'));
    expect($incomplet->fresh()->date_retrait)->toBeNull();
});

test('la recherche trouve par nom ou dossard et signale un mineur', function () {
    $enfant = User::factory()->create(['prenom' => 'Timéo', 'nom' => 'Rivière']);
    Participant::create(['id_user' => $enfant->id_user, 'date_naissance' => $this->evenement->date_evenement->copy()->subYears(11)->toDateString()]);
    inscriptionPayee($enfant, $this->course);

    $this->actingAs(admin(2));
    $this->get('/admin/retrait?q=riviere')->assertSee('Jade Rivière')->assertSee('Timéo Rivière')->assertSee('Mineur · 11 ans');
    $this->get('/admin/retrait?q=1042')->assertSee('Jade Rivière')->assertDontSee('Timéo');
});

test('le retrait est réservé au niveau 2, et seule cette page ouvre la caméra', function () {
    $this->actingAs(admin(1))->get('/admin/retrait')->assertForbidden();

    $this->actingAs(admin(2))->get('/admin/retrait')->assertOk()->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');
    $this->get('/admin')->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
});

test("un admin ouvre un poste : le lien n'est montré qu'une fois et seule l'empreinte est gardée", function () {
    $this->actingAs(admin(2));
    $this->post('/admin/retrait/postes', ['libelle' => 'Tente 2', 'valable_du' => today()->toDateString(), 'valable_au' => today()->toDateString()])
        ->assertSessionHas('retrait.lien');

    $jeton = basename(session('retrait.lien')['url']);
    expect(PosteRetrait::sole()->jeton_hash)->toBe(hash('sha256', $jeton));
    $this->get('/admin/retrait')->assertSee('Tente 2')->assertSee('Ouvert');
});

test('un bénévole ouvre le poste avec son prénom, puis remet un dossard noté à son nom', function () {
    $jeton = posteOuvert($this->evenement);

    $this->get("/retrait/{$jeton}")->assertOk()->assertSee('Merci de ton aide');
    $this->post("/retrait/{$jeton}/{$this->inscription->id_inscription}/remettre")->assertForbidden();

    $this->post("/retrait/{$jeton}/benevole", ['prenom' => 'Sacha'])->assertRedirect("/retrait/{$jeton}");
    $this->get("/retrait/{$jeton}?q=jade")->assertOk()->assertSee('Jade Rivière')->assertSee('Sacha')
        ->assertDontSee($this->inscription->participant->user->email)
        ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');

    $this->post("/retrait/{$jeton}/scan", ['jeton' => Billet::jeton($this->inscription)])->assertRedirect("/retrait/{$jeton}");
    $this->post("/retrait/{$jeton}/{$this->inscription->id_inscription}/remettre")->assertSessionHas('retrait.succes');

    expect(RemiseDossard::sole()->par)->toBe('Sacha (poste Entrée)');
});

test("un poste fermé, hors dates ou inconnu n'ouvre rien", function () {
    $ferme = posteOuvert($this->evenement);
    PosteRetrait::parJeton($ferme)->forceFill(['revoque_le' => now()])->save();
    $demain = posteOuvert($this->evenement, ['valable_du' => today()->addDay(), 'valable_au' => today()->addDay()]);

    foreach ([$ferme, $demain, str_repeat('a', 40)] as $jeton) {
        $this->get("/retrait/{$jeton}")->assertOk()->assertSee("Ce poste n'est pas ouvert", false);
        $this->post("/retrait/{$jeton}/benevole", ['prenom' => 'X'])->assertNotFound();
    }
});

test("un poste ne voit pas les inscriptions d'un autre événement", function () {
    $autre = Evenement::create(['nom' => 'Autre', 'date_evenement' => '2028-04-01', 'ouverture' => now(), 'fermeture' => now()->addYear(), 'statut' => Evenement::BROUILLON]);
    $jeton = posteOuvert($autre);
    $this->post("/retrait/{$jeton}/benevole", ['prenom' => 'Sacha']);

    $this->post("/retrait/{$jeton}/scan", ['jeton' => Billet::jeton($this->inscription)])->assertSessionHas('retrait.erreur');
    $this->post("/retrait/{$jeton}/{$this->inscription->id_inscription}/remettre")->assertNotFound();
    $this->get("/retrait/{$jeton}?q=jade")->assertDontSee('Jade Rivière');
});

test("une note interne apparaît discrètement à l'écran de retrait", function () {
    $i = Inscription::where('dossard', 1042)->sole();
    $admin = admin(2);
    \App\Models\NoteInterne::create(['texte' => 'Diabétique : sucre au ravitaillement', 'id_inscription' => $i->id_inscription, 'id_admin' => $admin->id_user]);

    $this->actingAs($admin)->get('/admin/retrait?q=1042')
        ->assertSee('Note · 1')->assertSee('Diabétique : sucre au ravitaillement');

    // Poste bénévole sans compte : l'indicateur seulement, le texte reste aux admins.
    auth()->logout();
    $jeton = posteOuvert($this->evenement);
    $this->post("/retrait/{$jeton}/benevole", ['prenom' => 'Sacha']);
    $this->get("/retrait/{$jeton}?q=1042")->assertOk()
        ->assertSee('Note : voir un responsable')->assertDontSee('Diabétique');
});
