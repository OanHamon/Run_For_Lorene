<?php

namespace App\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

/**
 * File d'attente sans worker permanent (hébergement mutualisé OVH : aucun processus de fond,
 * cron limité à une fois par heure).
 *
 * Les emails restent mis en file (table `jobs`) : un envoi raté est retenté, un échec définitif
 * apparaît dans « État technique ». Mais au lieu d'attendre un worker, la requête qui a mis un
 * email en file le traite elle-même juste après avoir envoyé sa réponse : le visiteur n'attend
 * pas (fastcgi_finish_request), l'email part dans la seconde. Le réveil externe (Reveil) vide
 * aussi la file chaque minute : il reprend les envois retentés et ceux d'une requête interrompue.
 *
 * Activé par FILE_SANS_WORKER=true (config rfl.file_sans_worker). Sur un serveur avec worker
 * (queue:work), laisser à false.
 */
class FileSansWorker
{
    /** Durée maximale d'un passage, en secondes (plusieurs billets PDF tiennent largement). */
    public const DUREE = 50;

    private static bool $enAttente = false;

    public static function enregistrer(Application $app): void
    {
        // Une commande artisan (cron, réveil) vide la file explicitement ; seules les requêtes
        // web le font à leur fin.
        if ($app->runningInConsole() && ! $app->runningUnitTests()) {
            return;
        }

        Event::listen(JobQueued::class, function () {
            self::$enAttente = (bool) config('rfl.file_sans_worker');
        });

        $app->terminating(function () {
            if (self::$enAttente) {
                self::$enAttente = false;
                self::vider();
            }
        });
    }

    /**
     * Traite la file jusqu'à ce qu'elle soit vide (ou DUREE secondes). Un seul passage à la fois :
     * pendant le rush, une requête qui trouve un passage en cours lui laisse ses emails.
     *
     * @return bool vrai si ce passage a eu lieu (faux : un autre était en cours)
     */
    public static function vider(int $secondes = self::DUREE): bool
    {
        if (config('queue.default') === 'sync') {
            return false;
        }

        $verrou = Cache::lock('file-sans-worker', $secondes + 30);
        if (! $verrou->get()) {
            return false;
        }

        try {
            ignore_user_abort(true);
            if (function_exists('set_time_limit')) {
                @set_time_limit($secondes + 30);
            }

            Artisan::call('queue:work', [
                '--stop-when-empty' => true,
                '--max-time' => $secondes,
                '--tries' => 3,
                '--backoff' => 60,
                '--sleep' => 0,
                '--memory' => 512,
            ]);
        } finally {
            $verrou->release();
        }

        return true;
    }
}
