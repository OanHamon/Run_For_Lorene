<?php

namespace App\Providers;

use App\Services\Paiement\Passerelle;
use App\Services\Paiement\PasserelleSimulation;
use App\Services\Paiement\PasserelleStripe;
use App\Support\TachesPlanifiees;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(StripeClient::class, fn () => new StripeClient((string) config('services.stripe.secret')));

        $this->app->bind(Passerelle::class, function ($app) {
            if (config('paiement.passerelle') === 'stripe') {
                return $app->make(PasserelleStripe::class);
            }

            // Simulation réservée au poste de développement et aux tests : un serveur de recette
            // public sans clé Stripe permettrait sinon de s'inscrire sans payer.
            if (! $app->environment('local', 'testing')) {
                throw new RuntimeException('Paiement : la simulation est réservée à APP_ENV=local, configure STRIPE_SECRET.');
            }

            return $app->make(PasserelleSimulation::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($proxies = config('rfl.proxies_de_confiance')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // Dernière exécution des tâches planifiées, affichée dans « État technique ».
        Event::listen(ScheduledTaskFinished::class, fn ($e) => TachesPlanifiees::noter($e->task, $e->task->exitCode === 0 || $e->task->exitCode === null));
        Event::listen(ScheduledTaskFailed::class, fn ($e) => TachesPlanifiees::noter($e->task, false));

        // 8 caractères minimum ; en production, refus des mots de passe déjà sortis dans une
        // fuite connue (service Have I Been Pwned, seul un préfixe du hash est envoyé).
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->uncompromised()
            : Password::min(8));
    }
}
