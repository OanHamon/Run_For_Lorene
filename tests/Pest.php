<?php

use App\Models\Commande;
use App\Models\Course;
use App\Models\Inscription;
use App\Models\Participant;
use App\Models\User;
use App\Services\Inscriptions\Questionnaires;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/** Réponses à toutes les questions actives d'un questionnaire ('sante' ou 'decharge'), par id_question. */
function reponses(string $type, string $valeur = 'non'): array
{
    return app(Questionnaires::class)->questions($type)
        ->mapWithKeys(fn ($q) => [$q->id_question => $valeur])
        ->all();
}

/** Étape Documents du tunnel complète : consentements, décharge et questionnaire de santé (pour ->set()). */
function documentsRemplis(): array
{
    return [
        'accepteReglement' => true,
        'accepteDecharge' => true,
        'accepteRgpd' => true,
        'questionnaires' => 'maintenant',
        'reponsesDecharge' => reponses('decharge', 'oui'),
        'reponsesSante' => reponses('sante'),
        'attestationSante' => true,
    ];
}

/** Inscription déjà réglée et validée pour $user sur $course (données de contexte des tests). */
function inscriptionPayee(User $user, Course $course, string $statut = Inscription::VALIDEE): Inscription
{
    Participant::firstOrCreate(['id_user' => $user->id_user], ['date_naissance' => '1980-01-01']);

    $commande = Commande::create([
        'date_commande' => now(),
        'montant_total' => 10,
        'statut' => Commande::PAYEE,
        'id_user' => $user->id_user,
    ]);

    return Inscription::create([
        'id_participant' => $user->id_user,
        'id_tarif' => $course->tarifs()->first()->id_tarif,
        'id_evenement' => $course->id_evenement,
        'id_commande' => $commande->id_commande,
        'statut' => $statut,
        'prix_paye' => 10,
    ]);
}
