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
            // Stripe impose au moins 30 minutes : c'est aussi la durée de réservation de la place.
            'expires_at' => now()->addMinutes(config('paiement.reservation_minutes'))->addMinute()->getTimestamp(),
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
