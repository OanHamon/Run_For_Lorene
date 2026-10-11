<?php

use App\Models\Commande;
use App\Models\Course;
use App\Models\Inscription;
use App\Models\Participant;
use App\Models\User;
use App\Services\Inscriptions\Questionnaires;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
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

/** Toutes les affirmations de la décharge cochées, par id_question. */
function affirmations(): array
{
    return array_map(fn () => true, reponses('decharge'));
}

/** Étape Documents du tunnel complète : décharge, questionnaire de santé et conditions (pour ->set()). */
function documentsRemplis(): array
{
    return [
        'accepteReglement' => true,
        'accepteDecharge' => true,
        'accepteRgpd' => true,
        'reponsesDecharge' => affirmations(),
        'reponsesSante' => reponses('sante'),
        'attestationSante' => true,
    ];
}

/** Remplit et valide chaque volet de l'étape Documents : décharge, santé (mineur), conditions. */
Testable::macro('remplirDocuments', function () {
    $this->set(documentsRemplis())->call('dechargeSuivant');

    if ($this->get('volet') === 'sante') {
        $this->call('santeSuivant');
    }

    return $this->call('conditionsSuivant');
});

/** Remplit tous les documents d'une inscription : règlement, RGPD, décharge, et santé pour un mineur. */
function documentsComplets(Inscription $inscription): Inscription
{
    $deposant = $inscription->participant->id_responsable ?? $inscription->id_participant;

    app(\App\Services\Inscriptions\Consentements::class)->enregistrer($inscription, true, $deposant, '127.0.0.1');
    app(Questionnaires::class)->remplirDecharge($inscription, affirmations(), $deposant, '127.0.0.1');

    if ($inscription->santeRequise()) {
        app(Questionnaires::class)->enregistrer($inscription, 'sante', reponses('sante'), true, $deposant);
    }

    return $inscription->fresh();
}

/** Compte admin vérifié, au niveau d'accès donné (1 lecture, 2 gestion, 3 super admin). */
function admin(int $niveau = 3): User
{
    $user = User::factory()->create();
    $user->admin()->create(['niveau_acces' => $niveau]);

    return $user->fresh();
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
