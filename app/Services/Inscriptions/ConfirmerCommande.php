<?php

namespace App\Services\Inscriptions;

use App\Models\Commande;
use App\Models\Inscription;
use App\Notifications\CommandeConfirmee;
use Illuminate\Support\Facades\DB;

/**
 * Commande réglée (paiement réussi ou rien à payer) : chaque inscription passe à « validée »
 * ou « dossier incomplet », et le payeur reçoit son récapitulatif. Idempotent : une commande
 * déjà payée n'est pas retraitée.
 */
class ConfirmerCommande
{
    public function __construct(private MettreAJourStatut $mettreAJourStatut) {}

    public function __invoke(Commande $commande): Commande
    {
        $confirmee = DB::transaction(function () use ($commande) {
            $commande = Commande::whereKey($commande->id_commande)->lockForUpdate()->first();

            if ($commande->statut === Commande::PAYEE) {
                return null;
            }

            $commande->forceFill(['statut' => Commande::PAYEE])->save();

            $commande->inscriptions()
                ->where('statut', Inscription::EN_ATTENTE_PAIEMENT)
                ->get()
                ->each(fn (Inscription $i) => ($this->mettreAJourStatut)($i));

            return $commande;
        });

        if ($confirmee) {
            $confirmee->user->notify(new CommandeConfirmee($confirmee));
        }

        return $commande->refresh();
    }
}
