<?php

namespace App\Services\Paiement;

use App\Models\Commande;
use App\Models\Paiement;

interface Passerelle
{
    /**
     * Ouvre une session de paiement pour la commande.
     *
     * @return array{reference: string, url: string}
     */
    public function creerSession(Commande $commande, Paiement $paiement): array;

    /**
     * Ferme une session encore ouverte. Faux si elle a déjà été payée (on ne doit alors
     * surtout pas détacher les inscriptions de la commande).
     */
    public function annuler(Paiement $paiement): bool;
}
