<?php

namespace App\Services\Inscriptions;

use App\Models\Inscription;
use Illuminate\Support\Facades\DB;

/**
 * Passage d'une inscription payée (ou gratuite) à « validée » ou « dossier incomplet »,
 * selon les consentements et le questionnaire de santé. Le dossard est attribué à l'entrée
 * dans l'état « validée ».
 */
class MettreAJourStatut
{
    public function __construct(private AttribuerDossard $attribuerDossard) {}

    public function __invoke(Inscription $inscription): Inscription
    {
        if (! in_array($inscription->statut, [Inscription::EN_ATTENTE_PAIEMENT, Inscription::DOSSIER_INCOMPLET, Inscription::BROUILLON], true)) {
            return $inscription;
        }

        return DB::transaction(function () use ($inscription) {
            $complet = $inscription->consentementsComplets() && ! $inscription->questionnaireManquant();

            $inscription->statut = $complet ? Inscription::VALIDEE : Inscription::DOSSIER_INCOMPLET;
            $inscription->save();

            if ($complet) {
                ($this->attribuerDossard)($inscription);
            }

            return $inscription->refresh();
        });
    }
}
