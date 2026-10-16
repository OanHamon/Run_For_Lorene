<?php

namespace App\Services\Admin;

use App\Http\Controllers\TexteLegalController;
use App\Models\Evenement;
use App\Models\TexteLegal;
use App\Services\Inscriptions\Consentements;
use Illuminate\Support\Collection;

/**
 * « Prêt à publier ? » : ce qu'il faut pour que le tunnel fonctionne de bout en bout. Une
 * course au moins, des tarifs sans trou pour chaque course, et une version des textes
 * obligatoires (règlement, décharge, RGPD). Sert à la checklist de la fiche et au blocage
 * de la publication ; calculé avec les dates saisies, même pas encore enregistrées.
 */
class PreparationEvenement
{
    public function __construct(private ControleTarifs $controle, private Consentements $consentements) {}

    /**
     * @return Collection<int, object{libelle: string, ok: bool, obligatoire: bool, detail: ?string, onglet: string, probleme: ?string}>
     */
    public function etapes(Evenement $evenement): Collection
    {
        $courses = $evenement->courses()->with('tarifs')->orderBy('heure_depart')->get();
        $etapes = collect([$this->etape(
            'Au moins une course',
            $courses->isNotEmpty(),
            onglet: 'courses',
            detail: $courses->isEmpty() ? 'Aucune course pour l\'instant.' : $courses->count().' course(s).',
            probleme: "l'événement n'a aucune course",
        )]);

        foreach ($courses as $course) {
            $course->setRelation('evenement', $evenement);
            $trous = $this->controle->trous($course);
            $premier = $trous->first();

            $etapes->push($this->etape(
                "Tarifs de « {$course->nom} »",
                $trous->isEmpty(),
                onglet: 'courses',
                detail: $premier ? 'Pas de tarif '.ControleTarifs::libelle($premier).($trous->count() > 1 ? ' (et '.($trous->count() - 1).' autre(s) trou(s))' : '').'.' : 'Un tarif pour chaque jour et chaque âge.',
                probleme: $premier ? "{$course->nom} n'a pas de tarif ".ControleTarifs::libelle($premier) : null,
            ));
        }

        $textes = $this->consentements->textes($evenement);

        foreach ([...TexteLegal::OBLIGATOIRES, ...TexteLegal::FACULTATIFS] as $type) {
            $texte = $textes->get($type);
            $obligatoire = in_array($type, TexteLegal::OBLIGATOIRES, true);
            $libelle = TexteLegalController::LIBELLES[$type];

            $etapes->push($this->etape(
                $libelle.($obligatoire ? '' : ' (facultatif)'),
                $texte !== null,
                obligatoire: $obligatoire,
                onglet: 'textes',
                detail: $texte ? "Version {$texte->version} du {$texte->date_publication->format('d/m/Y')}." : 'Aucun PDF publié.',
                probleme: 'il manque : '.mb_strtolower($libelle),
            ));
        }

        return $etapes;
    }

    /** @return list<string> ce qui empêche de publier (étapes obligatoires non remplies) */
    public function problemes(Evenement $evenement): array
    {
        return $this->etapes($evenement)
            ->filter(fn ($e) => $e->obligatoire && ! $e->ok)
            ->pluck('probleme')
            ->values()
            ->all();
    }

    private function etape(string $libelle, bool $ok, string $onglet, ?string $detail = null, ?string $probleme = null, bool $obligatoire = true): object
    {
        return (object) compact('libelle', 'ok', 'obligatoire', 'detail', 'onglet', 'probleme');
    }
}
