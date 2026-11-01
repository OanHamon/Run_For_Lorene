<?php

namespace App\Services\Inscriptions;

use App\Models\Commande;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Notifications\RelanceInscription;
use App\Support\ReglagesRelances;
use Illuminate\Support\Facades\Cache;

/**
 * Relances automatiques du spec, une seule fois chacune (marque gardée en cache) :
 * - documents : N jours avant l'événement (15 par défaut), à partir de l'heure réglée, pour
 *   chaque inscription en « dossier incomplet » ;
 * - paiement : N heures après une tentative non aboutie (24 par défaut), pour une commande
 *   toujours en attente.
 * Chacune se règle ou se met en pause dans l'admin (ReglagesRelances).
 */
class Relances
{
    /** Au-delà, la commande est considérée comme laissée de côté : pas de relance tardive. */
    public const JOURS_MAX_APRES_ABANDON = 7;

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

    /** @return int nombre de relances envoyées */
    public function paiements(): int
    {
        $reglage = ReglagesRelances::paiement();

        if (! $reglage['actif']) {
            return 0;
        }

        $envoyees = 0;

        Commande::with('user', 'paiements', 'inscriptions')
            ->where('statut', Commande::EN_COURS)
            ->where('date_commande', '>=', now()->subDays(self::JOURS_MAX_APRES_ABANDON))
            ->has('inscriptions') // un don seul n'est pas relancé : il reste un geste libre
            ->each(function (Commande $commande) use (&$envoyees, $reglage) {
                $derniere = $commande->paiements->sortByDesc('id_paiement')->first();

                // Tentative encore ouverte (la personne est peut-être en train de payer) : rien.
                if ($derniere?->statut === Paiement::EN_ATTENTE && $derniere->created_at->gt(now()->subMinutes(config('paiement.session_minutes') + 1))) {
                    return;
                }

                $abandon = $derniere?->updated_at ?? $commande->date_commande;

                if ($abandon->gt(now()->subHours($reglage['heures_apres']))) {
                    return;
                }

                $inscription = $commande->inscriptions->firstWhere('statut', Inscription::EN_ATTENTE_PAIEMENT);

                if ($inscription && Cache::add("relance-paiement:{$commande->id_commande}", now()->toIso8601String(), now()->addDays(30))) {
                    $commande->user->notify(new RelanceInscription($inscription));
                    $envoyees++;
                }
            });

        return $envoyees;
    }
}
