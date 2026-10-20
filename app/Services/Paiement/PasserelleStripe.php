<?php

namespace App\Services\Paiement;

use App\Models\Commande;
use App\Models\Paiement;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Stripe Checkout en mode paiement. Apple Pay / Google Pay sont activés dans le tableau de
 * bord Stripe : Checkout les affiche tout seul. Les montants passent en centimes ici et
 * uniquement ici.
 */
class PasserelleStripe implements Passerelle
{
    public function __construct(private StripeClient $stripe) {}

    public function creerSession(Commande $commande, Paiement $paiement): array
    {
        $commande->loadMissing('inscriptions.participant.user', 'inscriptions.tarif.course', 'don', 'user');

        $lignes = [];

        foreach ($commande->inscriptions as $inscription) {
            $lignes[] = $this->ligne(
                "{$inscription->tarif->course->nom} · {$inscription->participant->user->prenom} {$inscription->participant->user->nom}",
                (float) $inscription->prix_paye,
            );
        }

        if ($commande->don && (float) $commande->don->montant > 0) {
            $lignes[] = $this->ligne('Don à Effervescence Jeunes', (float) $commande->don->montant);
        }

        // Stripe refuse les lignes à 0 € : une épreuve gratuite dans une commande payante n'apparaît pas.
        $lignes = array_values(array_filter($lignes, fn ($l) => $l['price_data']['unit_amount'] > 0));

        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'payment',
            'locale' => 'fr',
            'line_items' => $lignes,
            'customer_email' => $commande->user->email,
            'client_reference_id' => (string) $commande->id_commande,
            'metadata' => [
                'id_commande' => $commande->id_commande,
                'id_paiement' => $paiement->id_paiement,
            ],
            'payment_intent_data' => [
                'description' => "Run For Lorène · commande {$commande->id_commande}",
            ],
            'success_url' => route('inscription.confirmation', $commande).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('inscription.echec', $commande),
            // La place reste réservée tant que la session peut être payée (Course::placesPrises).
            'expires_at' => now()->addMinutes(config('paiement.session_minutes'))->getTimestamp(),
        ], [
            'idempotency_key' => 'rfl-paiement-'.$paiement->id_paiement,
        ]);

        return ['reference' => $session->id, 'url' => $session->url];
    }

    public function annuler(Paiement $paiement): bool
    {
        try {
            $session = $this->stripe->checkout->sessions->retrieve($paiement->reference);

            if ($session->status === 'complete') {
                return false;
            }

            if ($session->status === 'open') {
                $this->stripe->checkout->sessions->expire($paiement->reference);
            }

            return true;
        } catch (ApiErrorException $e) {
            Log::warning("Stripe : impossible de fermer la session {$paiement->reference} : {$e->getMessage()}");

            // Dans le doute (session introuvable, réseau…), on ne détache pas la commande.
            return false;
        }
    }

    /**
     * Remboursement via l'API Stripe, sur le PaymentIntent de la session Checkout. L'id du
     * paiement part en métadonnée : le webhook charge.refunded retrouvera la ligne.
     */
    public function rembourser(Paiement $paiement, float $montant, ?string $motif): string
    {
        try {
            $intent = $this->paymentIntent($paiement);

            $refund = $this->stripe->refunds->create([
                'payment_intent' => $intent,
                'amount' => (int) round($montant * 100),
                'reason' => 'requested_by_customer',
                'metadata' => array_filter([
                    'id_paiement' => $paiement->id_paiement,
                    'motif' => $motif ? mb_substr($motif, 0, 500) : null,
                ]),
            ]);
        } catch (ApiErrorException $e) {
            Log::error("Stripe : remboursement refusé pour {$paiement->reference} : {$e->getMessage()}");

            throw new \RuntimeException("Stripe a refusé le remboursement : {$e->getMessage()}", previous: $e);
        }

        return $refund->id;
    }

    /** PaymentIntent (pi_…) d'une session Checkout payée. */
    public function paymentIntent(Paiement $paiement): string
    {
        $session = $this->stripe->checkout->sessions->retrieve($paiement->reference);

        if (! $session->payment_intent) {
            throw new \RuntimeException("La session {$paiement->reference} n'a pas de paiement associé.");
        }

        return is_string($session->payment_intent) ? $session->payment_intent : $session->payment_intent->id;
    }

    /** Session Checkout (cs_…) d'un PaymentIntent, pour un remboursement fait depuis le tableau de bord Stripe. */
    public function sessionDuPaiement(string $paymentIntentId): ?string
    {
        try {
            return $this->stripe->checkout->sessions->all(['payment_intent' => $paymentIntentId, 'limit' => 1])->data[0]->id ?? null;
        } catch (ApiErrorException $e) {
            Log::warning("Stripe : session introuvable pour {$paymentIntentId} : {$e->getMessage()}");

            return null;
        }
    }

    /** @return list<object{id: string, amount: int, status: string, created: int, metadata: object}> remboursements d'un paiement Stripe */
    public function remboursementsDe(string $chargeId): array
    {
        return $this->stripe->refunds->all(['charge' => $chargeId, 'limit' => 100])->data;
    }

    /** Frais Stripe d'un paiement réussi, en euros (null si indisponibles). */
    public function frais(string $paymentIntentId): ?float
    {
        try {
            $intent = $this->stripe->paymentIntents->retrieve($paymentIntentId, [
                'expand' => ['latest_charge.balance_transaction'],
            ]);

            $fee = $intent->latest_charge?->balance_transaction?->fee;

            return $fee !== null ? $fee / 100 : null;
        } catch (ApiErrorException $e) {
            Log::warning("Stripe : frais indisponibles pour {$paymentIntentId} : {$e->getMessage()}");

            return null;
        }
    }

    /** @return array<string, mixed> */
    private function ligne(string $libelle, float $montant): array
    {
        return [
            'quantity' => 1,
            'price_data' => [
                'currency' => 'eur',
                'unit_amount' => (int) round($montant * 100),
                'product_data' => ['name' => $libelle],
            ],
        ];
    }
}
