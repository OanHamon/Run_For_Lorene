<?php

namespace App\Services\Inscriptions;

use App\Models\Evenement;
use App\Models\Inscription;
use App\Notifications\RelanceInscription;
use App\Support\ReglagesRelances;
use Illuminate\Support\Facades\Cache;

/**
 * Relance automatique des documents manquants, une seule fois par inscription (marque gardée
 * en cache) : N jours avant l'événement (15 par défaut), à partir de l'heure réglée, pour
 * chaque inscription en « dossier incomplet ». Se règle et se met en pause dans l'admin
 * (ReglagesRelances), en pause par défaut. Pas de relance de paiement : l'inscription se fait
 * d'une traite, une commande non payée redevient un brouillon (AbandonnerCommandesOubliees).
 */
class Relances
{
    /** @return int nombre de relances envoyées */
    public function documents(): int
    {
        $reglage = ReglagesRelances::documents();

        // Lancée toutes les heures : rien avant l'heure d'envoi choisie.
        if (! $reglage['actif'] || now()->format('H:i') < $reglage['heure']) {
            return 0;
        }

        $evenements = Evenement::where('statut', Evenement::PUBLIE)
            ->whereDate('date_evenement', '>', today())
            ->whereDate('date_evenement', '<=', today()->addDays($reglage['jours_avant']))
            ->pluck('id_evenement');

        $envoyees = 0;

        Inscription::with('participant.user', 'participant.responsable', 'commande.user')
            ->whereIn('id_evenement', $evenements)
            ->where('statut', Inscription::DOSSIER_INCOMPLET)
            ->each(function (Inscription $inscription) use (&$envoyees) {
                $titulaire = $inscription->titulaire();

                if ($titulaire && Cache::add("relance-documents:{$inscription->id_inscription}", now()->toIso8601String(), now()->addDays(60))) {
                    $titulaire->notify(new RelanceInscription($inscription));
                    $envoyees++;
                }
            });

        return $envoyees;
    }
}
