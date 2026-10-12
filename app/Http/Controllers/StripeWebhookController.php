<?php

namespace App\Http\Controllers;

use App\Services\Paiement\EnregistrerPaiement;
use App\Services\Paiement\PasserelleStripe;
use App\Services\Paiement\Rembourser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Webhooks Stripe : seule source qui fait passer un paiement en « réussi » (spec).
 * Signature vérifiée ; traitement idempotent (voir EnregistrerPaiement).
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, EnregistrerPaiement $enregistrer): Response
    {
        $secret = config('services.stripe.webhook_secret');

        if (! $secret) {
            Log::error('Webhook Stripe reçu mais STRIPE_WEBHOOK_SECRET est vide.');

            return response('Webhook non configuré', 500);
        }

        try {
            $event = Webhook::constructEvent($request->getContent(), (string) $request->header('Stripe-Signature'), $secret);
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return response('Signature invalide', 400);
        }

        $session = $event->data->object;

        switch ($event->type) {
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                if ($session->payment_status === 'paid') {
                    $frais = $session->payment_intent
                        ? app(PasserelleStripe::class)->frais((string) $session->payment_intent)
                        : null;

                    $enregistrer->reussi($session->id, $session->amount_total / 100, $frais);
                }
                break;

            case 'checkout.session.expired':
            case 'checkout.session.async_payment_failed':
                $enregistrer->echoue($session->id);
                break;

            case 'charge.refunded':
                app(Rembourser::class)->depuisStripe($event->data->object, app(PasserelleStripe::class));
                break;
        }

        return response('ok');
    }
}
