<?php

namespace App\Services\Admin;

use App\Models\Evenement;
use App\Models\TexteLegal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Duplique un événement d'une année sur l'autre : l'événement en brouillon, ses courses
 * (plages de dossards comprises), leurs tarifs, ses options et tailles, et la dernière
 * version de chaque texte légal (republiée en version 1). Toutes les dates avancent d'un an.
 */
class DupliquerEvenement
{
    /** « Run For Lorène 3 » → « Run For Lorène 4 » ; sans numéro final : « Course » → « Course 2 ». */
    public static function nomSuivant(string $nom): string
    {
        if (preg_match('/^(.*?)(\d+)$/u', trim($nom), $m)) {
            return $m[1].((int) $m[2] + 1);
        }

        return trim($nom).' 2';
    }

    public function __invoke(Evenement $source): Evenement
    {
        $source->load('courses.tarifs', 'optionEvenements.declinaisons', 'texteLegals');

        return DB::transaction(function () use ($source) {
            $copie = Evenement::create([
                'nom' => self::nomSuivant($source->nom),
                'date_evenement' => $source->date_evenement->copy()->addYear(),
                'ouverture' => $source->ouverture->copy()->addYear(),
                'fermeture' => $source->fermeture->copy()->addYear(),
                'statut' => Evenement::BROUILLON,
            ]);

            foreach ($source->courses as $course) {
                $nouvelle = $copie->courses()->create($course->only([
                    'nom', 'distance', 'heure_depart', 'age_min', 'age_max', 'quota', 'dossard_debut', 'dossard_fin',
                ]));

                foreach ($course->tarifs as $tarif) {
                    $nouvelle->tarifs()->create([
                        ...$tarif->only(['libelle', 'montant', 'age_min', 'age_max']),
                        'valide_du' => $tarif->valide_du->copy()->addYear(),
                        'valide_au' => $tarif->valide_au->copy()->addYear(),
                    ]);
                }
            }

            foreach ($source->optionEvenements as $option) {
                $nouvelle = $copie->optionEvenements()->create($option->only(['libelle', 'prix']));

                foreach ($option->declinaisons as $declinaison) {
                    $nouvelle->declinaisons()->create($declinaison->only(['libelle', 'stock']));
                }
            }

            $derniers = $source->texteLegals->sortByDesc('version')->unique('type_texte');

            foreach ($derniers as $texte) {
                $chemin = TexteLegal::chemin($copie, $texte->type_texte, 1);

                if (Storage::disk('local')->exists($texte->fichier)) {
                    Storage::disk('local')->copy($texte->fichier, $chemin);
                }

                $copie->texteLegals()->create([
                    'type_texte' => $texte->type_texte,
                    'version' => 1,
                    'date_publication' => today(),
                    'fichier' => $chemin,
                ]);
            }

            return $copie;
        });
    }
}
