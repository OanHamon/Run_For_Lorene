<?php

namespace App\Services\Paiement;

use App\Models\Commande;
use App\Models\Inscription;
use App\Models\Paiement;
use Illuminate\Support\Facades\DB;

/**
 * « Modifier ma commande » alors qu'elle attend son paiement : la commande est abandonnée,
 * ses inscriptions redeviennent des brouillons modifiables. Refusé si un paiement a déjà
 * abouti côté prestataire.
 */
class AbandonnerCommande
{
    public function __construct(private Passerelle $passerelle) {}

    public function __invoke(Commande $commande): bool
    {
        return DB::transaction(function () use ($commande) {
            $commande = Commande::whereKey($commande->id_commande)->lockForUpdate()->first();

            if ($commande->statut !== Commande::EN_COURS) {
                return false;
            }

            foreach ($commande->paiements()->where('statut', Paiement::EN_ATTENTE)->get() as $paiement) {
                if (! $this->passerelle->annuler($paiement)) {
                    return false;
                }
                $paiement->forceFill(['statut' => Paiement::ECHOUE])->save();
            }

            $commande->inscriptions()
                ->where('statut', Inscription::EN_ATTENTE_PAIEMENT)
                ->update([
                    'statut' => Inscription::BROUILLON,
                    'id_commande' => null,
                    'prix_paye' => 0,
                    'updated_at' => now(),
                ]);

            $commande->don()->delete();
            $commande->forceFill(['statut' => Commande::ABANDONNEE])->save();

            return true;
        });
    }
}
