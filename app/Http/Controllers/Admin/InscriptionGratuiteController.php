<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Inscription;
use App\Models\User;
use App\Notifications\InscriptionOfferte;
use App\Services\Admin\InscrireGratuitement;
use App\Services\Admin\Journal;
use App\Support\EvenementAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Inscription manuelle gratuite (cas particuliers), niveau 2, en deux temps :
 * 1. l'email du titulaire : on retrouve son compte et ses participants (ou on prévient qu'il
 *    n'existe pas : vérifier l'orthographe, ou créer d'abord le compte) ;
 * 2. le choix du participant (existant ou nouveau), la course et les consentements.
 */
class InscriptionGratuiteController extends Controller
{
    public function create(Request $request): View
    {
        $evenement = EvenementAdmin::choisi($request);
        abort_unless($evenement, 404);

        $email = Str::lower(trim((string) $request->query('email')));
        $emailValide = $email !== '' && Validator::make(['email' => $email], ['email' => 'email:filter|max:254'])->passes();
        $compte = $emailValide ? User::with('participant', 'participantsGeres.user')->where('email', $email)->first() : null;

        // Inscriptions déjà en cours à l'événement, pour griser les participants concernés.
        $dejaInscrits = $compte
            ? Inscription::where('id_evenement', $evenement->id_evenement)
                ->whereIn('id_participant', $compte->participantsGeres->pluck('id_user')->push($compte->id_user))
                ->whereNotIn('statut', [Inscription::BROUILLON, Inscription::ANNULEE])
                ->get()
                ->keyBy('id_participant')
            : collect();

        return view('admin.inscriptions.gratuite', [
            'evenement' => $evenement,
            'courses' => $evenement->courses()->orderBy('heure_depart')->get(),
            'email' => $email,
            'emailValide' => $emailValide,
            'compte' => $compte,
            'dejaInscrits' => $dejaInscrits,
        ]);
    }

    public function store(Request $request, InscrireGratuitement $inscrire, Journal $journal): RedirectResponse
    {
        $evenement = EvenementAdmin::choisi($request);
        abort_unless($evenement, 404);

        $donnees = $request->validate([
            'email' => ['required', 'email:filter', 'max:254'],
            'participant' => ['required', 'string', 'max:20'],
            'prenom' => ['nullable', 'string', 'max:100'],
            'nom' => ['nullable', 'string', 'max:100'],
            'date_naissance' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'date_naissance_titulaire' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'id_course' => ['required', Rule::exists('courses', 'id_course')->where('id_evenement', $evenement->id_evenement)],
            'consentements' => ['accepted'],
            'motif' => ['nullable', 'string', 'max:255'],
            'prevenir' => ['boolean'],
        ], [
            'participant.required' => 'Choisis qui participe.',
            'consentements.accepted' => 'Le règlement et la politique de protection des données doivent avoir été acceptés.',
        ]);

        $inscription = $inscrire(Course::findOrFail($donnees['id_course']), $donnees, $request->ip());

        $journal->noter('inscription.gratuite', $inscription, [
            'participant' => $inscription->participant->user->name,
            'course' => $inscription->tarif->course->nom,
            'titulaire' => $inscription->titulaire()?->email,
            'motif' => $donnees['motif'] ?? null,
        ]);

        $titulaire = $inscription->titulaire();
        if ($request->boolean('prevenir') && $titulaire) {
            $titulaire->notify(new InscriptionOfferte($inscription));
        }

        return redirect()->route('admin.inscriptions.show', $inscription)
            ->with('statut', "Inscription gratuite créée ({$inscription->libelleStatut()})."
                .($request->boolean('prevenir') && $titulaire ? " Email envoyé à {$titulaire->email}." : ''));
    }
}
