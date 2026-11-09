<?php

namespace App\Services;

use App\Support\FileSansWorker;
use App\Support\TachesPlanifiees;
use Cron\CronExpression;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Réveil du site sur un hébergement mutualisé (OVH Personal : pas de worker, cron au mieux une
 * fois par heure). Appelé chaque minute par un service externe (adresse /reveil/{clé}) et, en
 * secours, par le cron horaire d'OVH (cron-ovh.php) :
 *
 * 1. lance les tâches planifiées `rfl:*` arrivées à échéance, dans ce processus PHP (lancer
 *    `php artisan` à part est impossible depuis le serveur web) ;
 * 2. vide la file d'attente des emails (FileSansWorker).
 *
 * Rattrapage : une tâche tourne dès que sa dernière échéance est postérieure à son dernier
 * passage. Un réveil manqué (service externe en panne, cron horaire à une minute quelconque)
 * ne fait donc jamais sauter la purge de 3 h ou une relance : elle part au réveil suivant.
 */
class Reveil
{
    public const DERNIER = 'reveil:dernier';

    public function __construct(private readonly Schedule $schedule) {}

    /** @return array{taches: array<string, bool>, file: bool} tâches lancées (réussie ou non) et passage de la file */
    public function __invoke(): array
    {
        Cache::forever(self::DERNIER, now()->toIso8601String());

        // Charge routes/console.php (commandes et planning) si ce n'est pas déjà fait (requête web).
        Artisan::all();

        $taches = [];
        foreach ($this->schedule->events() as $event) {
            $nom = TachesPlanifiees::nom($event);
            if (str_starts_with($nom, 'rfl:') && $this->aLancer($event, $nom)) {
                $taches[$nom] = $this->lancer($nom);
            }
        }

        return ['taches' => $taches, 'file' => FileSansWorker::vider(40)];
    }

    /** Dernière échéance passée de la tâche postérieure à son dernier passage (ou jamais lancée). */
    private function aLancer(Event $event, string $nom): bool
    {
        $fuseau = $event->timezone ?: config('app.timezone');
        $maintenant = Carbon::now($fuseau);
        $echeance = Carbon::instance((new CronExpression($event->expression))->getPreviousRunDate($maintenant, 0, true));

        $derniere = TachesPlanifiees::derniere($nom);

        return ! $derniere || Carbon::parse($derniere['le'])->lt($echeance);
    }

    /** Lance la tâche une seule fois à la fois (deux réveils rapprochés ne la doublent pas). */
    private function lancer(string $nom): bool
    {
        $verrou = Cache::lock("reveil:{$nom}", 3600);
        if (! $verrou->get()) {
            return false;
        }

        try {
            $reussie = Artisan::call($nom) === 0;
        } catch (Throwable $e) {
            report($e);
            $reussie = false;
        } finally {
            $verrou->release();
        }

        TachesPlanifiees::noterNom($nom, $reussie);

        return $reussie;
    }
}
