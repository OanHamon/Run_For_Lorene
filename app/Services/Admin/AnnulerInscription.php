<?php

namespace App\Services\Admin;

use App\Models\Inscription;
use App\Services\Paiement\AbandonnerCommande;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Annulation d'une inscription par un admin. Le dossard est libéré ; le remboursement
 * éventuel se fait à part, depuis la commande.
 *
 * En attente de paiement, la commande (au montant figé) est abandonnée et sa session de
 * paiement fermée : les autres inscriptions de la commande redeviennent des brouillons que le
 * payeur pourra régler à nouveau, comme quand il supprime lui-même une inscription.
 */
class AnnulerInscription
{
    public const ANNULABLES = [Inscription::EN_ATTENTE_PAIEMENT, Inscription::VALIDEE, Inscription::DOSSIER_INCOMPLET];

    public function __construct(private AbandonnerCommande $abandonnerCommande) {}

    /**
     * @return array{dossard: ?int, autres: int} dossard libéré, inscriptions de la commande remises en brouillon
     *
     * @throws ValidationException (clé « annulation »)
     */
    public function __invoke(Inscription $inscription): array
    {
        if (! in_array($inscription->statut, self::ANNULABLES, true)) {
            throw ValidationException::withMessages(['annulation' => 'Cette inscription ne peut pas être annulée.']);
        }

        return DB::transaction(function () use ($inscription) {
            $resultat = ['dossard' => $inscription->dossard, 'autres' => 0];
            $enAttente = $inscription->statut === Inscription::EN_ATTENTE_PAIEMENT;

            $inscription->forceFill(['statut' => Inscription::ANNULEE, 'dossard' => null])->save();

            if ($enAttente) {
                $commande = $inscription->commande;
                $resultat['autres'] = $commande->inscriptions()->where('statut', Inscription::EN_ATTENTE_PAIEMENT)->count();

                if (! ($this->abandonnerCommande)($commande)) {
                    throw ValidationException::withMessages(['annulation' => 'Un paiement est peut-être en cours de validation pour cette commande. Réessaie dans quelques minutes.']);
                }
            }

            return $resultat;
        });
    }
}
