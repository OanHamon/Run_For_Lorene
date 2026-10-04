<?php

namespace App\Services\Inscriptions;

use App\Models\Inscription;
use App\Services\Paiement\AbandonnerCommande;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Suppression par le participant d'une inscription pas encore payée (brouillon ou en attente
 * de paiement). Une inscription réglée ne se supprime pas ici : l'association rembourse
 * au cas par cas.
 *
 * Si l'inscription faisait partie d'une commande en attente, la commande est abandonnée
 * (son montant est figé) : les autres inscriptions redeviennent des brouillons à repayer.
 */
class SupprimerInscription
{
    public function __construct(private AbandonnerCommande $abandonnerCommande) {}

    /**
     * @return array{autres: int, don: float} inscriptions de la même commande remises en brouillon, et don de cette commande
     *
     * @throws ValidationException (clé "suppression")
     */
    public function __invoke(Inscription $inscription): array
    {
        $resultat = ['autres' => 0, 'don' => 0.0];

        if ($inscription->statut === Inscription::EN_ATTENTE_PAIEMENT) {
            $commande = $inscription->commande;
            $resultat['don'] = (float) ($commande->don?->montant ?? 0);
            $resultat['autres'] = $commande->inscriptions()->whereKeyNot($inscription->id_inscription)->count();

            if (! ($this->abandonnerCommande)($commande)) {
                throw ValidationException::withMessages(['suppression' => 'Un paiement est peut-être en cours de validation pour cette inscription. Réessaie dans quelques minutes.']);
            }

            $inscription->refresh();
        }

        if ($inscription->statut !== Inscription::BROUILLON) {
            throw ValidationException::withMessages(['suppression' => "Cette inscription est réglée : pour l'annuler, contacte l'association."]);
        }

        // Jamais soumise ni payée : ses consentements partent avec elle (clé étrangère en cascade).
        DB::transaction(fn () => $inscription->delete());

        return $resultat;
    }
}
