<?php

namespace App\Services\Inscriptions;

use App\Models\Document;
use App\Models\Evenement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Purge RGPD (spec) : 12 mois après l'événement, les documents de ses inscriptions sont
 * supprimés pour de bon, archives comprises. Consentements, questionnaires et réponses de
 * santé partent avec eux (clés étrangères en cascade). Les inscriptions, commandes et
 * paiements restent : ils font partie de la comptabilité.
 */
class PurgerDocuments
{
    public const DELAI_MOIS = 12;

    /** @return array<int, int> nombre de documents purgés, par id_evenement */
    public function __invoke(): array
    {
        $resultat = [];

        $evenements = Evenement::whereNull('documents_purges_le')
            ->whereDate('date_evenement', '<=', today()->subMonths(self::DELAI_MOIS))
            ->get();

        foreach ($evenements as $evenement) {
            $resultat[$evenement->id_evenement] = DB::transaction(function () use ($evenement) {
                $nombre = Document::withTrashed()
                    ->whereIn('id_inscription', $evenement->inscriptions()->select('id_inscription'))
                    ->forceDelete();

                $evenement->forceFill(['documents_purges_le' => now()])->save();

                return $nombre;
            });

            Log::info("Purge RGPD : {$resultat[$evenement->id_evenement]} document(s) supprimé(s) pour {$evenement->nom}.");
        }

        return $resultat;
    }
}
