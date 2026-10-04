<?php

namespace App\Services\Inscriptions;

use App\Models\Course;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\Participant;
use App\Models\Tarif;

/**
 * Règles d'accès à une épreuve, vérifiées à l'affichage, à la sélection et de nouveau au
 * moment de passer commande (les données ont pu changer entre-temps : date de naissance
 * modifiée, tarif expiré, épreuve remplie par d'autres participants…).
 */
class Eligibilite
{
    /**
     * @return string|null message d'erreur pour le participant, null si tout est bon
     */
    public function erreur(Participant $participant, Course $course, Evenement $evenement): ?string
    {
        if (! $evenement->inscriptionsOuvertes()) {
            return 'Les inscriptions ne sont pas ouvertes en ce moment.';
        }

        if ($course->id_evenement !== $evenement->id_evenement) {
            return "Cette épreuve n'appartient pas à l'événement.";
        }

        $age = $participant->ageAu($evenement->date_evenement);

        if (! $course->accepteAge($age)) {
            return "{$course->nom} n'est pas ouverte à l'âge du participant ({$age} ans le jour J).";
        }

        if (! $this->tarif($participant, $course, $evenement)) {
            return "Aucun tarif n'est disponible aujourd'hui pour {$course->nom}.";
        }

        $restantes = $course->placesRestantes();
        if ($restantes !== null && $restantes <= 0) {
            return "{$course->nom} est complète.";
        }

        return null;
    }

    public function tarif(Participant $participant, Course $course, Evenement $evenement): ?Tarif
    {
        return $course->tarifPour($participant->ageAu($evenement->date_evenement));
    }

    /** Participant déjà inscrit (hors brouillon) à cet événement ? */
    public function dejaInscrit(Participant $participant, Evenement $evenement): bool
    {
        return Inscription::where('id_evenement', $evenement->id_evenement)
            ->where('id_participant', $participant->id_user)
            ->whereNotIn('statut', [Inscription::BROUILLON, Inscription::ANNULEE])
            ->exists();
    }
}
