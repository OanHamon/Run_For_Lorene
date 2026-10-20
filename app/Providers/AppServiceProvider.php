<?php

namespace App\Providers;

use App\Services\Paiement\Passerelle;
use App\Services\Paiement\PasserelleSimulation;
use App\Services\Paiement\PasserelleStripe;
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

            if ($app->isProduction()) {
                throw new RuntimeException('Paiement : la simulation est interdite en production, configure STRIPE_SECRET.');
            }

            return $app->make(PasserelleSimulation::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 8 caractères minimum ; en production, refus des mots de passe déjà sortis dans une
        // fuite connue (service Have I Been Pwned, seul un préfixe du hash est envoyé).
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->uncompromised()
            : Password::min(8));
    }
}
