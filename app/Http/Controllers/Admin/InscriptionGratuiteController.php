<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Notifications\InscriptionOfferte;
use App\Services\Admin\InscrireGratuitement;
use App\Services\Admin\Journal;
use App\Support\EvenementAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Inscription manuelle gratuite (cas particuliers) : niveau 2. */
class InscriptionGratuiteController extends Controller
{
    public function create(Request $request): View
    {
        $evenement = EvenementAdmin::choisi($request);
        abort_unless($evenement, 404);

        return view('admin.inscriptions.gratuite', [
            'evenement' => $evenement,
            'courses' => $evenement->courses()->orderBy('heure_depart')->get(),
        ]);
    }

    public function store(Request $request, InscrireGratuitement $inscrire, Journal $journal): RedirectResponse
    {
        $evenement = EvenementAdmin::choisi($request);
        abort_unless($evenement, 404);

        $donnees = $request->validate([
            'id_course' => ['required', Rule::exists('courses', 'id_course')->where('id_evenement', $evenement->id_evenement)],
            'email' => ['required', 'email:filter', 'max:254'],
            'pour' => ['required', 'in:titulaire,gere'],
            'prenom' => ['required', 'string', 'max:100'],
            'nom' => ['required', 'string', 'max:100'],
            'date_naissance' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'titulaire_prenom' => ['nullable', 'string', 'max:100'],
            'titulaire_nom' => ['nullable', 'string', 'max:100'],
            'consentements' => ['accepted'],
            'motif' => ['nullable', 'string', 'max:255'],
            'prevenir' => ['boolean'],
        ], [
            'consentements.accepted' => 'Le règlement et la politique de protection des données doivent avoir été acceptés.',
        ]);

        $inscription = $inscrire(Course::findOrFail($donnees['id_course']), $donnees, $request->ip());

        $journal->noter('inscription.gratuite', $inscription, [
            'participant' => $inscription->participant->user->name,
            'course' => $inscription->tarif->course->nom,
            'motif' => $donnees['motif'] ?? null,
        ]);

        if ($request->boolean('prevenir')) {
            $inscription->titulaire()?->notify(new InscriptionOfferte($inscription));
        }

        return redirect()->route('admin.inscriptions.show', $inscription)
            ->with('statut', "Inscription gratuite créée ({$inscription->libelleStatut()}).");
    }
}
