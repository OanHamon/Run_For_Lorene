<?php

use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

test("les emails partent par l'API Brevo (HTTPS), avec la clé du .env et les pièces jointes", function () {
    $requetes = [];
    app()->instance(HttpClientInterface::class, new MockHttpClient(function (string $methode, string $url, array $options) use (&$requetes) {
        $requetes[] = compact('methode', 'url', 'options');

        return new MockResponse(json_encode(['messageId' => '<abc@brevo>']), ['http_code' => 201]);
    }));
    config(['mail.default' => 'brevo', 'mail.mailers.brevo.key' => 'xkeysib-test', 'mail.from.address' => 'contact@exemple.fr']);
    Mail::purge('brevo');

    Mail::raw('Ton billet est joint.', fn ($m) => $m->to('jade@exemple.fr')->subject('Billet')
        ->attachData('%PDF-1.7 test', 'billet.pdf', ['mime' => 'application/pdf']));

    expect($requetes)->toHaveCount(1);
    $corps = json_decode($requetes[0]['options']['body'], true);

    expect($requetes[0]['methode'])->toBe('POST')
        ->and($requetes[0]['url'])->toBe('https://api.brevo.com/v3/smtp/email')
        ->and(implode("\n", $requetes[0]['options']['headers']))->toContain('api-key: xkeysib-test')
        ->and($corps['to'][0]['email'])->toBe('jade@exemple.fr')
        ->and($corps['subject'])->toBe('Billet')
        ->and($corps['attachment'][0]['name'])->toBe('billet.pdf');
});
