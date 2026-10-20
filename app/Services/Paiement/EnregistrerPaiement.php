<?php

namespace App\Services\Paiement;

use App\Models\Commande;
use App\Models\Paiement;
use App\Services\Inscriptions\ConfirmerCommande;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Issue d'une tentative de paiement, telle que rapportée par le webhook Stripe (seule
 * source qui fait foi) ou par la page de simulation. Idempotent : un même événement reçu
 * deux fois ne change rien.
 */
class EnregistrerPaiement
{
    public function __construct(private ConfirmerCommande $confirmerCommande) {}

    /**
     * Paiement encaissé. La commande n'est confirmée que si le montant reçu, en euros, couvre
     * son total : sinon le paiement est enregistré (l'argent est là) et signalé à l'admin
     * dans le tableau de bord (Statistiques::paiementsAVerifier).
     */
    public function reussi(string $reference, ?float $montant = null, ?float $frais = null, string $devise = 'eur'): ?Paiement
    {
        $paiement = DB::transaction(function () use ($reference, $montant, $frais, $devise) {
            $paiement = Paiement::where('reference', $reference)->lockForUpdate()->first();

            if (! $paiement) {
                Log::warning("Paiement réussi reçu pour une référence inconnue : {$reference}");

                return null;
            }

            if ($paiement->statut === Paiement::REUSSI) {
                return null; // déjà traité
            }

            // Même une tentative marquée « échouée » (fermée par une relance) peut avoir été payée
            // juste avant : l'argent est encaissé, on l'enregistre.
            $paiement->forceFill([
                'statut' => Paiement::REUSSI,
                'montant' => $montant ?? $paiement->montant,
                'frais' => $frais,
                'date_paiement' => now(),
            ])->save();

            if ($montant !== null && abs($montant - (float) $paiement->commande->montant_total) > 0.009) {
                Log::error("Paiement {$reference} : montant encaissé {$montant} € différent du total de la commande {$paiement->commande->montant_total} €.");
            }

            return $paiement;
        });

        if (! $paiement) {
            return null;
        }

        $commande = $paiement->commande;

        if (strtolower($devise) !== 'eur' || (float) $paiement->montant + 0.009 < (float) $commande->montant_total) {
            Log::error("Paiement {$reference} : {$paiement->montant} {$devise} ne couvre pas la commande {$commande->id_commande} ({$commande->montant_total} €), commande non confirmée.");

            return $paiement;
        }

        match ($commande->statut) {
            Commande::EN_COURS => ($this->confirmerCommande)($commande),
            Commande::PAYEE => Log::error("Commande {$commande->id_commande} payée deux fois (paiement {$reference}) : remboursement à faire depuis Stripe."),
            default => Log::error("Paiement {$reference} reçu sur la commande {$commande->id_commande} au statut « {$commande->statut} » : à traiter par un admin."),
        };

        return $paiement;
    }

    public function echoue(string $reference): void
    {
        Paiement::where('reference', $reference)
            ->where('statut', Paiement::EN_ATTENTE)
            ->update(['statut' => Paiement::ECHOUE, 'updated_at' => now()]);
    }
}
