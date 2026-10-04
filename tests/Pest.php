<?php

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

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
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

/** Inscription déjà réglée et validée pour $user sur $course (données de contexte des tests). */
function inscriptionPayee(App\Models\User $user, App\Models\Course $course, string $statut = App\Models\Inscription::VALIDEE): App\Models\Inscription
{
    App\Models\Participant::firstOrCreate(['id_user' => $user->id_user], ['date_naissance' => '1980-01-01']);

    $commande = App\Models\Commande::create([
        'date_commande' => now(),
        'montant_total' => 10,
        'statut' => App\Models\Commande::PAYEE,
        'id_user' => $user->id_user,
    ]);

    return App\Models\Inscription::create([
        'id_participant' => $user->id_user,
        'id_tarif' => $course->tarifs()->first()->id_tarif,
        'id_evenement' => $course->id_evenement,
        'id_commande' => $commande->id_commande,
        'statut' => $statut,
        'prix_paye' => 10,
    ]);
}
