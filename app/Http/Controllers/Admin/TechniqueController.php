<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\Statistiques;
use App\Services\Reveil;
use App\Support\TachesPlanifiees;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * « État technique » (super admin) : de quoi repérer une panne avant les participants.
 * File d'attente des emails, tâches planifiées, réglages risqués, paiements à vérifier.
 */
class TechniqueController extends Controller
{
    public function __invoke(Schedule $schedule): View
    {
        $plusVieux = DB::table('jobs')->min('created_at');

        return view('admin.technique', [
            'file' => [
                'en_attente' => DB::table('jobs')->count(),
                'plus_vieux' => $plusVieux ? Carbon::createFromTimestamp($plusVieux) : null,
                'echecs' => DB::table('failed_jobs')->count(),
                'derniers_echecs' => DB::table('failed_jobs')->orderByDesc('id')->limit(5)->get()->map(fn ($j) => (object) [
                    'le' => Carbon::parse($j->failed_at),
                    'tache' => json_decode($j->payload)->displayName ?? '?',
                    'erreur' => str($j->exception)->before("\n")->limit(160)->toString(),
                ]),
            ],
            'taches' => collect($schedule->events())->map(fn ($e) => (object) [
                'nom' => TachesPlanifiees::nom($e),
                'frequence' => $e->getExpression(),
                'derniere' => TachesPlanifiees::derniere(TachesPlanifiees::nom($e)),
            ])->filter(fn ($t) => str_starts_with($t->nom, 'rfl:'))->values(),
            'reglages' => $this->reglages(),
            'paiements' => Statistiques::paiementsAVerifier()->count(),
        ]);
    }

    /**
     * Réveil externe (mutualisé) : sans lui, ni relances, ni purge RGPD, ni envois programmés,
     * et les emails retentés restent en file. Attendu chaque minute ; alerte après 10 minutes.
     *
     * @return array{libelle: string, valeur: string, ok: bool, aide: ?string}
     */
    private function reveil(bool $prod): array
    {
        $dernier = Cache::get(Reveil::DERNIER);
        $actif = (bool) config('rfl.reveil_cle');

        return [
            'libelle' => 'Réveil du site (chaque minute)',
            'valeur' => ! $actif ? 'désactivé' : ($dernier ? 'dernier passage '.Carbon::parse($dernier)->diffForHumans() : 'jamais appelé'),
            'ok' => ! $prod || ! config('rfl.file_sans_worker') || ($actif && $dernier && Carbon::parse($dernier)->gt(now()->subMinutes(10))),
            'aide' => 'Sur le mutualisé : cron-job.org appelle /reveil/{REVEIL_CLE} chaque minute, et le cron OVH lance cron-ovh.php chaque heure.',
        ];
    }

    /** @return list<array{libelle: string, valeur: string, ok: bool, aide: ?string}> */
    private function reglages(): array
    {
        $prod = app()->isProduction();

        return [
            ['libelle' => 'Environnement', 'valeur' => app()->environment(), 'ok' => true, 'aide' => null],
            ['libelle' => 'Mode débogage', 'valeur' => config('app.debug') ? 'activé' : 'désactivé', 'ok' => ! ($prod && config('app.debug')), 'aide' => 'À désactiver en production : il affiche le détail des erreurs.'],
            ['libelle' => 'Envoi des emails', 'valeur' => (string) config('mail.default'),
                'ok' => ! ($prod && in_array(config('mail.default'), ['log', 'array'], true)) && ! (config('mail.default') === 'brevo' && ! config('mail.mailers.brevo.key')),
                'aide' => 'En production, les emails partent par l\'API Brevo (MAIL_MAILER=brevo et BREVO_API_KEY).'],
            ['libelle' => 'File d\'attente', 'valeur' => (string) config('queue.default').(config('rfl.file_sans_worker') ? ', vidée après chaque page' : ''), 'ok' => config('queue.default') !== 'sync' || ! $prod, 'aide' => 'Sur le mutualisé, FILE_SANS_WORKER=true ; sur un serveur, un worker (queue:work).'],
            $this->reveil($prod),
            ['libelle' => 'Paiement', 'valeur' => config('paiement.passerelle') === 'stripe' ? 'Stripe' : 'simulation', 'ok' => config('paiement.passerelle') === 'stripe' || ! $prod, 'aide' => 'Sans clé Stripe, le paiement est simulé.'],
            ['libelle' => 'Signature des webhooks Stripe', 'valeur' => config('services.stripe.webhook_secret') ? 'configurée' : 'absente', 'ok' => (bool) config('services.stripe.webhook_secret') || config('paiement.passerelle') !== 'stripe', 'aide' => 'Sans elle, les paiements ne sont jamais confirmés.'],
            ['libelle' => 'Mode beta', 'valeur' => config('rfl.beta') ? 'activé' : 'désactivé',
                'ok' => ! (config('rfl.beta') && str_starts_with((string) config('services.stripe.secret'), 'sk_live')),
                'aide' => 'En beta, Stripe doit rester en mode test (clé sk_test_…) : sinon les testeurs paieraient pour de vrai.'],
            ['libelle' => 'Codes de connexion dans les logs', 'valeur' => config('auth.otp_dans_les_logs') ? 'oui' : 'non', 'ok' => ! ($prod && config('auth.otp_dans_les_logs')), 'aide' => 'Réservé au poste de développement (ignoré en production).'],
        ];
    }
}
