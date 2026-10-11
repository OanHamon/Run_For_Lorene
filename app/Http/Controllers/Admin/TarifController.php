<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Tarif;
use App\Services\Admin\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Tarifs d'une course : période de validité et tranche d'âge facultative. Modifier un tarif
 * ne change pas les inscriptions déjà passées (prix historisé).
 */
class TarifController extends Controller
{
    public function __construct(private Journal $journal) {}

    public function store(Request $request, Course $course): RedirectResponse
    {
        $tarif = $course->tarifs()->create($this->valider($request, 'tarif'));
        $this->journal->noter('tarif.creation', $tarif, ['course' => $course->nom, 'libelle' => $tarif->libelle, 'montant' => (string) $tarif->montant]);

        return redirect()->route('admin.courses.edit', $course)->with('statut', 'Tarif ajouté.');
    }

    public function edit(Tarif $tarif): View
    {
        return view('admin.tarifs.form', ['tarif' => $tarif->load('course.evenement')]);
    }

    public function update(Request $request, Tarif $tarif): RedirectResponse
    {
        $tarif->fill($this->valider($request));
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

    /** @return array<string, mixed> */
    private function valider(Request $request, string $sac = 'default'): array
    {
        return $request->validateWithBag($sac, [
            'libelle' => ['required', 'string', 'max:100'],
            'montant' => ['required', 'numeric', 'min:0', 'max:999999.99', 'decimal:0,2'],
            'valide_du' => ['required', 'date'],
            'valide_au' => ['required', 'date', 'after_or_equal:valide_du'],
            'age_min' => ['nullable', 'integer', 'min:0', 'max:120'],
            'age_max' => ['nullable', 'integer', 'min:0', 'max:120', Rule::when($request->filled('age_min'), 'gte:age_min')],
        ], [
            'age_max.gte' => "L'âge maximum doit être supérieur ou égal à l'âge minimum.",
            'valide_au.after_or_equal' => 'La fin de validité doit être égale ou postérieure au début.',
        ]);
    }
}
