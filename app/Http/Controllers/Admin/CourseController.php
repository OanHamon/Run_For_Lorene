<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\Tarif;
use App\Services\Admin\ControleTarifs;
use App\Services\Admin\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

/** Courses d'un événement : âges, quota, plage de dossards ; les tarifs se gèrent sur la fiche de la course. */
class CourseController extends Controller
{
    public function __construct(private Journal $journal) {}

    public function create(Evenement $evenement): View
    {
        return view('admin.courses.form', ['evenement' => $evenement, 'course' => new Course]);
    }

    public function store(Request $request, Evenement $evenement): RedirectResponse
    {
        $course = $evenement->courses()->create($this->valider($request, $evenement));
        $this->journal->noter('course.creation', $course, ['nom' => $course->nom]);

        return redirect()->route('admin.courses.edit', $course)->with('statut', 'Course créée. Ajoute maintenant son tarif.');
    }

    public function edit(Course $course, ControleTarifs $controle): View
    {
        $course->load(['evenement', 'tarifs' => fn ($q) => $q->orderBy('valide_du')->withCount('inscriptions')]);
        $trous = $controle->trous($course);

        // Le formulaire d'ajout propose de combler le premier trou, sinon toute la période d'inscription.
        $premier = $trous->first();
        $nouveauTarif = new Tarif([
            'libelle' => $course->tarifs->isEmpty() ? 'Plein tarif' : null,
            'valide_du' => $premier?->du ?? $course->evenement->ouverture->copy()->startOfDay(),
            'valide_au' => $premier?->au ?? $course->evenement->fermeture->copy()->startOfDay(),
            'age_min' => $premier && $premier->age_min > 0 ? $premier->age_min : null,
            'age_max' => $premier && $premier->age_max < ControleTarifs::AGE_MAX ? $premier->age_max : null,
        ]);

        return view('admin.courses.form', [
            'evenement' => $course->evenement,
            'course' => $course,
            'inscriptions' => $this->inscriptions($course)->count(),
            'trous' => $trous,
            'nouveauTarif' => $nouveauTarif,
        ]);
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $course->fill($this->valider($request, $course->evenement, $course));
        $modifs = $course->getDirty();
        $course->save();

        if ($modifs) {
            $this->journal->noter('course.modification', $course, array_map(fn ($v) => (string) $v, $modifs));
        }

        return redirect()->route('admin.courses.edit', $course)->with('statut', 'Course enregistrée.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        if ($this->inscriptions($course)->exists()) {
            return back()->with('erreur', 'Cette course a déjà des inscriptions : elle ne peut plus être supprimée.');
        }

        DB::transaction(function () use ($course) {
            $course->tarifs()->delete();
            $course->delete();
        });

        $this->journal->noter('course.suppression', $course, ['nom' => $course->nom]);

        return redirect()->route('admin.evenements.show', [$course->id_evenement, 'onglet' => 'courses'])->with('statut', 'Course supprimée.');
    }

    private function inscriptions(Course $course)
    {
        return Inscription::whereIn('id_tarif', $course->tarifs()->select('id_tarif'));
    }

    /** @return array<string, mixed> */
    private function valider(Request $request, Evenement $evenement, ?Course $course = null): array
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'distance' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'heure_depart' => ['required', 'date_format:H:i,H:i:s'],
            'age_min' => ['nullable', 'integer', 'min:0', 'max:120'],
            'age_max' => ['nullable', 'integer', 'min:0', 'max:120', Rule::when($request->filled('age_min'), 'gte:age_min')],
            'quota' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'dossard_debut' => ['nullable', 'required_with:dossard_fin', 'integer', 'min:1', 'max:65535'],
            'dossard_fin' => ['nullable', 'required_with:dossard_debut', 'integer', 'min:1', 'max:65535', 'gte:dossard_debut'],
        ], [
            'age_max.gte' => "L'âge maximum doit être supérieur ou égal à l'âge minimum.",
            'dossard_fin.gte' => 'Le dernier dossard doit être supérieur ou égal au premier.',
        ]);

        // La plage ne doit chevaucher celle d'aucune autre course de l'événement (dossard unique par événement),
        // ni laisser de côté des dossards déjà attribués à cette course.
        validator($donnees)->after(function (Validator $v) use ($donnees, $evenement, $course) {
            if (! isset($donnees['dossard_debut'], $donnees['dossard_fin'])) {
                return;
            }

            $chevauche = $evenement->courses()
                ->when($course, fn ($q) => $q->whereKeyNot($course->id_course))
                ->whereNotNull('dossard_debut')
                ->where('dossard_debut', '<=', $donnees['dossard_fin'])
                ->where('dossard_fin', '>=', $donnees['dossard_debut'])
                ->first();

            if ($chevauche) {
                $v->errors()->add('dossard_debut', "Cette plage chevauche celle de « {$chevauche->nom} » ({$chevauche->dossard_debut}–{$chevauche->dossard_fin}).");
            }

            if ($course && $this->inscriptions($course)->whereNotNull('dossard')
                ->where(fn ($q) => $q->where('dossard', '<', $donnees['dossard_debut'])->orWhere('dossard', '>', $donnees['dossard_fin']))
                ->exists()) {
                $v->errors()->add('dossard_debut', 'Des dossards déjà attribués sortent de cette plage : élargis-la plutôt.');
            }
        })->validate();

        return $donnees;
    }
}
