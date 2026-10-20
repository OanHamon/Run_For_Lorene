<?php

namespace App\Services\Inscriptions;

use App\Models\Inscription;
use Illuminate\Support\Carbon;

/**
 * Correction de la date de naissance d'un participant déjà inscrit (espace participant ou
 * admin). La nouvelle date ne doit ni sortir le participant de la tranche d'âge de sa course
 * ou de son tarif, ni le faire passer de mineur à majeur (ou l'inverse) : le questionnaire de
 * santé en dépend. Dans ces cas, on annule et on refait l'inscription.
 */
class ChangementDateNaissance
{
    /**
     * @param  string  $qui  sujet du message (« Camille », « le participant »)
     * @return string|null message d'erreur, null si la nouvelle date est acceptable
     */
    public function erreur(Inscription $inscription, string $date, string $qui): ?string
    {
        $participant = $inscription->participant;

        if ($date === $participant->date_naissance->toDateString()) {
            return null;
        }

        $jourJ = $inscription->evenement->date_evenement;
        $age = (int) Carbon::parse($date)->diffInYears($jourJ);
        $course = $inscription->tarif->course;
        $tarif = $inscription->tarif;

        return match (true) {
            ! $course->accepteAge($age) => "Avec cette date, {$qui} aurait {$age} ans le jour J : ce n'est pas compatible avec {$course->nom}.",
            ($tarif->age_min !== null && $age < $tarif->age_min) || ($tarif->age_max !== null && $age > $tarif->age_max) => "Avec cette date, {$qui} aurait {$age} ans le jour J : ce n'est plus la tranche d'âge du tarif « {$tarif->libelle} ».",
            ($age < 18) !== ($participant->ageAu($jourJ) < 18) => "Avec cette date, {$qui} passerait de ".($age < 18 ? 'majeur à mineur' : 'mineur à majeur').' le jour J : le questionnaire de santé en dépend.',
            default => null,
        };
    }
}
