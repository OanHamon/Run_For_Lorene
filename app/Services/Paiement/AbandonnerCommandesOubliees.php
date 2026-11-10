<?php

namespace App\Services\Paiement;

use App\Models\Commande;
use App\Models\Paiement;

/**
 * Inscription d'une traite : une commande restée « en cours » après la fin de sa session de
 * paiement est abandonnée, ses inscriptions redeviennent des brouillons. Le webhook
 * checkout.session.expired le fait déjà ; ce passage planifié rattrape un webhook perdu ou
 * une session jamais créée. Une session payée entre-temps n'est pas touchée
 * (AbandonnerCommande refuse quand Stripe l'annonce payée).
 */
class AbandonnerCommandesOubliees
{
    /** Marge après la fin de la session, pour laisser arriver le webhook de paiement. */
    public const MARGE_MINUTES = 5;

    public function __construct(private AbandonnerCommande $abandonner) {}

    /** @return int nombre de commandes abandonnées */
    public function __invoke(): int
    {
        $limite = now()->subMinutes(config('paiement.session_minutes') + self::MARGE_MINUTES);
        $abandonnees = 0;

        Commande::where('statut', Commande::EN_COURS)
            ->where('date_commande', '<', $limite)
            ->whereDoesntHave('paiements', fn ($p) => $p->where('statut', Paiement::REUSSI))
            ->whereDoesntHave('paiements', fn ($p) => $p->where('statut', Paiement::EN_ATTENTE)->where('created_at', '>=', $limite))
            ->each(function (Commande $commande) use (&$abandonnees) {
                if (($this->abandonner)($commande)) {
                    $abandonnees++;
                }
            });

        return $abandonnees;
    }
}
