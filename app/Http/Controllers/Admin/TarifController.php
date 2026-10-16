<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Tarif;
use App\Services\Admin\ControleTarifs;
use App\Services\Admin\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

/**
 * Tarifs d'une course : période de validité et tranche d'âge facultative. Modifier un tarif
 * ne change pas les inscriptions déjà passées (prix historisé).
 */
class TarifController extends Controller
{
    public function __construct(private Journal $journal, private ControleTarifs $controle) {}

    public function store(Request $request, Course $course): RedirectResponse
    {
        $tarif = $course->tarifs()->create($this->valider($request, $course, null, 'tarif'));
        $this->journal->noter('tarif.creation', $tarif, ['course' => $course->nom, 'libelle' => $tarif->libelle, 'montant' => (string) $tarif->montant]);

        return redirect()->route('admin.courses.edit', $course)->with('statut', 'Tarif ajouté.');
    }

    public function edit(Tarif $tarif): View
    {
        return view('admin.tarifs.form', ['tarif' => $tarif->load('course.evenement')]);
    }

    public function update(Request $request, Tarif $tarif): RedirectResponse
    {
        $tarif->fill($this->valider($request, $tarif->course, $tarif));
        $modifs = $tarif->getDirty();
        $tarif->save();

        if ($modifs) {
            $this->journal->noter('tarif.modification', $tarif, array_map(fn ($v) => (string) $v, $modifs));
        }

        return redirect()->route('admin.courses.edit', $tarif->id_course)->with('statut', 'Tarif enregistré.');
    }

    public function destroy(Tarif $tarif): RedirectResponse
    {
        if ($tarif->inscriptions()->exists()) {
            return back()->with('erreur', 'Ce tarif a déjà servi à des inscriptions : il ne peut plus être supprimé. Raccourcis plutôt sa période de validité.');
        }

        $tarif->delete();
        $this->journal->noter('tarif.suppression', $tarif, ['libelle' => $tarif->libelle]);

        return redirect()->route('admin.courses.edit', $tarif->id_course)->with('statut', 'Tarif supprimé.');
    }

    /**
     * Règles simples, puis garde-fous de dates et d'âges (ControleTarifs) : période comprise
     * dans celle des inscriptions, tranche d'âge compatible avec la course, aucun recouvrement
     * avec un autre tarif de la course (mêmes jours et mêmes âges).
     *
     * @return array<string, mixed>
     */
    private function valider(Request $request, Course $course, ?Tarif $tarif = null, string $sac = 'default'): array
    {
        $evenement = $course->evenement;
        $ouverture = $evenement->ouverture->toDateString();
        $fermeture = $evenement->fermeture->toDateString();

        $donnees = $request->validateWithBag($sac, [
            'libelle' => ['required', 'string', 'max:100'],
            'montant' => ['required', 'numeric', 'min:0', 'max:999999.99', 'decimal:0,2'],
            'valide_du' => ['required', 'date', "after_or_equal:{$ouverture}", "before_or_equal:{$fermeture}"],
            'valide_au' => ['required', 'date', 'after_or_equal:valide_du', "before_or_equal:{$fermeture}"],
            'age_min' => ['nullable', 'integer', 'min:0', 'max:120'],
            'age_max' => ['nullable', 'integer', 'min:0', 'max:120', Rule::when($request->filled('age_min'), 'gte:age_min')],
        ], [
            'age_max.gte' => "L'âge maximum doit être supérieur ou égal à l'âge minimum.",
            'valide_du.after_or_equal' => "Un tarif ne peut pas commencer avant l'ouverture des inscriptions ({$evenement->ouverture->format('d/m/Y')}).",
            'valide_du.before_or_equal' => "Un tarif ne peut pas commencer après la fermeture des inscriptions ({$evenement->fermeture->format('d/m/Y')}).",
            'valide_au.after_or_equal' => 'La fin de validité doit être égale ou postérieure au début.',
            'valide_au.before_or_equal' => "Un tarif ne peut pas finir après la fermeture des inscriptions ({$evenement->fermeture->format('d/m/Y')}).",
        ]);

        $donnees['age_min'] = isset($donnees['age_min']) ? (int) $donnees['age_min'] : null;
        $donnees['age_max'] = isset($donnees['age_max']) ? (int) $donnees['age_max'] : null;

        validator($donnees)->after(function (Validator $v) use ($course, $tarif, $donnees) {
            if (! $this->controle->ageCompatible($course, $donnees['age_min'], $donnees['age_max'])) {
                $v->errors()->add('age_min', 'Cette tranche d\'âge ('.ControleTarifs::libelleAges($donnees['age_min'], $donnees['age_max'])
                    .') ne croise pas celle de la course ('.ControleTarifs::libelleAges($course->age_min, $course->age_max).') : le tarif ne servirait jamais.');
            }

            if ($autre = $this->controle->chevauchement($course, $donnees, $tarif)) {
                $v->errors()->add('valide_du', "Ce tarif recouvre « {$autre->libelle} » (du {$autre->valide_du->format('d/m/Y')} au {$autre->valide_au->format('d/m/Y')}, "
                    .ControleTarifs::libelleAges($autre->age_min, $autre->age_max).') : pour les mêmes jours, les tranches d\'âge ne doivent pas se croiser.');
            }
        })->validateWithBag($sac);

        return $donnees;
    }
}
