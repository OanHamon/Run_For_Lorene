<?php

use App\Models\User;
use App\Notifications\CodeEmailNotification;
use App\Notifications\MotDePasseModifie;
use App\Services\Auth\CodeEmail;

function utilisateurMail(): User
{
    $user = new User(['email' => 'camille@example.test']);
    $user->prenom = 'Camille';

    return $user;
}

it('met le code en avant dans le mail de connexion, aux couleurs de la charte', function () {
    $html = (string) (new CodeEmailNotification('482913'))->toMail(utilisateurMail())->render();

    expect($html)
        ->toContain('Bonjour Camille,')
        ->toContain('482913')
        ->toContain('confirmer ton adresse email')
        ->toContain('Il est valable '.CodeEmail::VALIDITE_MINUTES.' minutes.')
        ->toContain('images/logo-run-for-lorene.png')
        ->toContain('images/logo-effervescence-jeunes.png')
        // Thème rfl inliné : pastille vert profond, chiffres moutarde.
        ->toContain('background-color: #006B61')
        ->toContain('color: #D9B21E')
        ->not->toContain('**482913**');
});

it("adapte le texte du code pour l'administration", function () {
    $html = (string) (new CodeEmailNotification('482913', CodeEmail::ADMIN))->toMail(utilisateurMail())->render();

    expect($html)->toContain("l'espace d'administration");
});

it('ne laisse plus de texte anglais dans les mails avec bouton', function () {
    $html = (string) (new MotDePasseModifie)->toMail(utilisateurMail())->render();

    expect($html)
        ->toContain('Si le bouton « Me connecter » ne fonctionne pas')
        ->toContain('Un événement Effervescence Jeunes')
        ->not->toContain('trouble clicking')
        ->not->toContain('All rights reserved');
});
