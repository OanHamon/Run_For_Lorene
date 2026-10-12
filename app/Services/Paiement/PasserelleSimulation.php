<?php

namespace App\Services\Paiement;

use App\Models\Commande;
use App\Models\Paiement;
use Illuminate\Support\Str;

/**
 * Remplace Stripe hors production quand aucune clé n'est configurée : une page locale
 * permet de simuler un paiement réussi, refusé ou abandonné.
 */
class PasserelleSimulation implements Passerelle
{
    public function creerSession(Commande $commande, Paiement $paiement): array
    {
        return [
            'reference' => 'sim_'.Str::lower(Str::random(24)),
            'url' => route('paiement.simulation', $paiement),
        ];
    }

    public function annuler(Paiement $paiement): bool
    {
        return $paiement->statut !== Paiement::REUSSI;
    }

    public function rembourser(Paiement $paiement, float $montant, ?string $motif): string
    {
        return 'sim_re_'.Str::lower(Str::random(24));
    }
}
