<?php

namespace App\Services\Paiement;

use App\Models\Commande;
use App\Models\Course;
use App\Models\Inscription;
use App\Models\Paiement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Nouvelle tentative de paiement pour une commande en cours (premier essai ou « Réessayer »).
 * La tentative réserve les places pendant 30 minutes. Si la réservation d'une tentative
 * précédente a expiré entre-temps, on revérifie qu'il reste de la place.
 */
class DemarrerPaiement
{
    public function __construct(private Passerelle $passerelle) {}

    /**
     * @return string URL de la page de paiement
     *
     * @throws ValidationException (clé "paiement")
     */
    public function __invoke(Commande $commande): string
    {
        $paiement = DB::transaction(function () use ($commande) {
            $commande = Commande::whereKey($commande->id_commande)->lockForUpdate()->first();

            if ($commande->statut !== Commande::EN_COURS) {
                throw ValidationException::withMessages(['paiement' => "Cette commande n'est plus en attente de paiement."]);
            }

            $inscriptions = $commande->inscriptions()->with('tarif', 'participant.user')->get();
            $idsCourses = $inscriptions->pluck('tarif.id_course')->unique()->sort()->values();
            $courses = Course::whereIn('id_course', $idsCourses)->orderBy('id_course')->lockForUpdate()->get()->keyBy('id_course');

            $demandes = [];
            foreach ($inscriptions as $inscription) {
                if ($inscription->statut !== Inscription::EN_ATTENTE_PAIEMENT) {
                    continue;
                }

                $course = $courses[$inscription->tarif->id_course];
                $demandes[$course->id_course] = ($demandes[$course->id_course] ?? 0) + 1;

                // Places restantes sans compter celles que cette commande tient déjà.
                $restantes = $course->placesRestantes(saufCommande: $commande->id_commande);

                if ($restantes !== null && $demandes[$course->id_course] > $restantes) {
                    throw ValidationException::withMessages(['paiement' => "Désolé, {$course->nom} est désormais complète. Modifie ta commande pour choisir une autre épreuve."]);
                }
            }

            // Une seule tentative ouverte à la fois : les précédentes sont fermées.
            foreach ($commande->paiements()->where('statut', Paiement::EN_ATTENTE)->get() as $ancien) {
                if (! $this->passerelle->annuler($ancien)) {
                    throw ValidationException::withMessages(['paiement' => 'Un paiement est déjà en cours de validation pour cette commande. Patiente quelques instants.']);
                }
                $ancien->forceFill(['statut' => Paiement::ECHOUE])->save();
            }

            return Paiement::create([
                'prestataire' => config('paiement.passerelle'),
                'reference' => 'tmp_'.Str::uuid(),
                'montant' => $commande->montant_total,
                'statut' => Paiement::EN_ATTENTE,
                'id_commande' => $commande->id_commande,
            ]);
        });

        try {
            $session = $this->passerelle->creerSession($commande, $paiement);
        } catch (\Throwable $e) {
            $paiement->delete();

            throw $e;
        }

        $paiement->forceFill(['reference' => $session['reference']])->save();

        return $session['url'];
    }
}
