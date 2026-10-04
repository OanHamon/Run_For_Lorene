<?php

namespace App\Services\Inscriptions;

use App\Models\Course;
use App\Models\Inscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Premier numéro libre dans la plage de la course (spec). À appeler dans une transaction :
 * la ligne de la course est verrouillée pour que deux validations simultanées ne prennent
 * pas le même numéro ; la contrainte UNIQUE(id_evenement, dossard) reste le filet de sécurité.
 */
class AttribuerDossard
{
    public function __invoke(Inscription $inscription): ?int
    {
        if ($inscription->dossard !== null) {
            return $inscription->dossard;
        }

        return DB::transaction(function () use ($inscription) {
            $course = Course::whereKey($inscription->tarif->id_course)->lockForUpdate()->first();

            if ($course->dossard_debut === null || $course->dossard_fin === null) {
                Log::warning("Alerte dossards : aucune plage définie pour la course {$course->nom} (inscription {$inscription->id_inscription}).");

                return null;
            }

            $pris = Inscription::where('id_evenement', $inscription->id_evenement)
                ->whereBetween('dossard', [$course->dossard_debut, $course->dossard_fin])
                ->pluck('dossard')
                ->flip();

            for ($numero = $course->dossard_debut; $numero <= $course->dossard_fin; $numero++) {
                if (! isset($pris[$numero])) {
                    $inscription->forceFill(['dossard' => $numero])->save();

                    return $numero;
                }
            }

            Log::warning("Alerte dossards : plage {$course->dossard_debut}-{$course->dossard_fin} pleine pour la course {$course->nom} (inscription {$inscription->id_inscription}).");

            return null;
        });
    }
}
