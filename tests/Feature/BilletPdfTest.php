<?php

use App\Models\Commande;
use App\Models\Course;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\User;
use App\Notifications\CommandeConfirmee;
use App\Notifications\InscriptionValidee;
use App\Support\Billet;
use App\Support\Charte;
use App\Support\Empreinte;

beforeEach(function () {
    $this->seed();
    $this->evenement = Evenement::first();
    $this->course = Course::where('id_evenement', $this->evenement->id_evenement)->first();
    $this->inscription = inscriptionPayee(User::factory()->create(['prenom' => 'Jade', 'nom' => 'Rivière']), $this->course);
    $this->inscription->update(['dossard' => 1042]);
});

test("l'empreinte calculée par le serveur est celle du navigateur (code.js)", function () {
    // Référence donnée par cellulesDuJeton('RFL-1042-0123456789abcdef0123') de resources/js/track/code.js.
    $attendu = '1010010101011100101100000010011001001011101110011101000100110100101100111100000000101111000001101111000001000111111011100111010011001000000000001001101001101110011001010010110011000101010011000100110111111001000010111111001001000000100110111010101111000100000010';

    expect(implode('', Empreinte::cellules('RFL-1042-0123456789abcdef0123')))->toBe($attendu)
        ->and(Empreinte::cellules('pas un billet'))->toBeNull()
        ->and(Empreinte::cellules('RFL-99999999999-0123456789abcdef0123'))->toBeNull();
});

test("l'empreinte du billet est une image PNG", function () {
    $png = Empreinte::png(Billet::jeton($this->inscription), 2);

    expect(substr($png, 0, 8))->toBe("\x89PNG\r\n\x1a\n")
        ->and(getimagesizefromstring($png)[0])->toBeGreaterThan(600);
});

test('les couleurs de App\Support\Charte sont celles de app.css', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    foreach (Charte::COULEURS as $nom => $hex) {
        expect($css)->toMatch('/--color-'.preg_quote($nom, '/').':\s*'.preg_quote($hex, '/').';/i');
    }
});

test("le billet PDF s'ouvre par un lien signé, seulement pour une inscription validée", function () {
    $reponse = $this->get(Billet::urlPdf($this->inscription))->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    expect($reponse->getContent())->toStartWith('%PDF')
        ->and($reponse->headers->get('Content-Disposition'))->toContain('billet-run-for-lorene-1042.pdf');

    $this->get(route('billet.pdf', $this->inscription))->assertForbidden();

    $this->inscription->update(['statut' => Inscription::DOSSIER_INCOMPLET]);
    $this->get(Billet::urlPdf($this->inscription))->assertNotFound();
});

test('la page billet et l\'espace proposent le billet à imprimer', function () {
    $this->get(Billet::url($this->inscription))->assertOk()
        ->assertSee('Télécharger le billet à imprimer (PDF)')
        ->assertSee(e(Billet::urlPdf($this->inscription)), false);

    $this->actingAs($this->inscription->participant->user)
        ->get(route('espace.inscription', $this->inscription))->assertOk()
        ->assertSee('Télécharger le billet à imprimer (PDF)');
});

test("l'email « inscription validée » joint le billet PDF", function () {
    $mail = (new InscriptionValidee($this->inscription))->toMail($this->inscription->participant->user);

    expect($mail->rawAttachments)->toHaveCount(1)
        ->and($mail->rawAttachments[0]['name'])->toBe('billet-run-for-lorene-1042.pdf')
        ->and($mail->rawAttachments[0]['options']['mime'])->toBe('application/pdf')
        ->and($mail->rawAttachments[0]['data'])->toStartWith('%PDF')
        ->and(implode(' ', $mail->introLines))->toContain('joint à cet email');
});

test("l'email de commande joint un billet par inscription validée, pas pour un dossier incomplet", function () {
    $commande = Commande::find($this->inscription->id_commande);
    $autre = inscriptionPayee(User::factory()->create(), $this->course, Inscription::DOSSIER_INCOMPLET);
    $autre->update(['id_commande' => $commande->id_commande]);

    $mail = (new CommandeConfirmee($commande))->toMail($this->inscription->participant->user);

    expect($mail->rawAttachments)->toHaveCount(1)
        ->and($mail->rawAttachments[0]['name'])->toBe('billet-run-for-lorene-1042.pdf')
        ->and(implode(' ', $mail->introLines))->toContain('Le billet, à présenter au retrait des dossards, est joint');
});
