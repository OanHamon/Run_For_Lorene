<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Declinaison;
use App\Models\Evenement;
use App\Models\InscriptionDeclinaison;
use App\Models\OptionEvenement;
use App\Services\Admin\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Options d'un événement (le t-shirt) et leurs déclinaisons (une par taille, avec son stock ;
 * stock vide = illimité, 0 = taille épuisée, grisée dans le tunnel).
 */
class OptionController extends Controller implements HasMiddleware
{
    /** Écrans masqués tant que les options sont désactivées (config rfl.options). */
    public static function middleware(): array
    {
        return [function ($request, $next) {
            abort_unless(config('rfl.options'), 404);

            return $next($request);
        }];
    }

    public function __construct(private Journal $journal) {}

    public function create(Evenement $evenement): View
    {
        return view('admin.options.form', ['evenement' => $evenement, 'option' => new OptionEvenement(['prix' => 0])]);
    }

    public function store(Request $request, Evenement $evenement): RedirectResponse
    {
        $option = $evenement->optionEvenements()->create($this->valider($request));
        $this->journal->noter('option.creation', $option, ['libelle' => $option->libelle]);

        return redirect()->route('admin.options.edit', $option)->with('statut', 'Option créée. Ajoute maintenant ses tailles.');
    }

    public function edit(OptionEvenement $option): View
    {
        $option->load('evenement', 'declinaisons');

        $vendues = InscriptionDeclinaison::whereIn('id_declinaison', $option->declinaisons->pluck('id_declinaison'))
            ->selectRaw('id_declinaison, sum(quantite) as total')
            ->groupBy('id_declinaison')
            ->pluck('total', 'id_declinaison');

        return view('admin.options.form', ['evenement' => $option->evenement, 'option' => $option, 'vendues' => $vendues]);
    }

    public function update(Request $request, OptionEvenement $option): RedirectResponse
    {
        $option->fill($this->valider($request));
        $modifs = $option->getDirty();
        $option->save();

        if ($modifs) {
            $this->journal->noter('option.modification', $option, array_map(fn ($v) => (string) $v, $modifs));
        }

        return redirect()->route('admin.options.edit', $option)->with('statut', 'Option enregistrée.');
    }

    public function destroy(OptionEvenement $option): RedirectResponse
    {
        if (InscriptionDeclinaison::whereIn('id_declinaison', $option->declinaisons()->select('id_declinaison'))->exists()) {
            return back()->with('erreur', 'Cette option a déjà été choisie : elle ne peut plus être supprimée. Passe ses tailles en stock 0 pour la retirer de la vente.');
        }

        DB::transaction(fn () => $option->delete()); // déclinaisons supprimées en cascade
        $this->journal->noter('option.suppression', $option, ['libelle' => $option->libelle]);

        return redirect()->route('admin.evenements.show', [$option->id_evenement, 'onglet' => 'options'])->with('statut', 'Option supprimée.');
    }

    public function ajouterDeclinaison(Request $request, OptionEvenement $option): RedirectResponse
    {
        $donnees = $request->validateWithBag('declinaison', $this->reglesDeclinaison());
        $declinaison = $option->declinaisons()->create($donnees);
        $this->journal->noter('option.modification', $option, ['taille ajoutée' => $declinaison->libelle, 'stock' => $declinaison->stock ?? 'illimité']);

        return redirect()->route('admin.options.edit', $option)->with('statut', "Taille {$declinaison->libelle} ajoutée.");
    }

    public function modifierDeclinaison(Request $request, Declinaison $declinaison): RedirectResponse
    {
        $declinaison->fill($request->validateWithBag("declinaison{$declinaison->id_declinaison}", $this->reglesDeclinaison()));
        $modifs = $declinaison->getDirty();
        $declinaison->save();

        if ($modifs) {
            $this->journal->noter('option.modification', $declinaison->optionEvenement, ['taille' => $declinaison->libelle, ...$modifs]);
        }

        return redirect()->route('admin.options.edit', $declinaison->id_option)->with('statut', "Taille {$declinaison->libelle} enregistrée.");
    }

    public function supprimerDeclinaison(Declinaison $declinaison): RedirectResponse
    {
        if (InscriptionDeclinaison::where('id_declinaison', $declinaison->id_declinaison)->exists()) {
            return back()->with('erreur', "La taille {$declinaison->libelle} a déjà été choisie : passe plutôt son stock à 0.");
        }

        $declinaison->delete();
        $this->journal->noter('option.modification', $declinaison->optionEvenement, ['taille supprimée' => $declinaison->libelle]);

        return redirect()->route('admin.options.edit', $declinaison->id_option)->with('statut', "Taille {$declinaison->libelle} supprimée.");
    }

    /** @return array<string, mixed> */
    private function valider(Request $request): array
    {
        return $request->validate([
            'libelle' => ['required', 'string', 'max:100'],
            'prix' => ['required', 'numeric', 'min:0', 'max:999999.99', 'decimal:0,2'],
        ]);
    }

    /** @return array<string, list<string>> */
    private function reglesDeclinaison(): array
    {
        return [
            'libelle' => ['required', 'string', 'max:50'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
