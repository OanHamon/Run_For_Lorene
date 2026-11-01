<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Inscription;
use App\Models\RemiseDossard;
use App\Services\Admin\Journal;
use App\Support\Csv;
use App\Support\EvenementAdmin;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * « Exports et listes » : tous les fichiers à sortir du site au même endroit. Les exports
 * existants (inscrits, dossards, dons) plus la liste d'émargement par course (page à imprimer
 * ou à enregistrer en PDF depuis le navigateur) et la liste des retraits de dossards.
 * Chaque sortie de données personnelles est notée au journal.
 */
class ExportController extends Controller
{
    public function __construct(private Journal $journal) {}

    public function index(Request $request): View
    {
        $evenement = EvenementAdmin::choisi($request);

        return view('admin.exports.index', [
            'evenement' => $evenement,
            'courses' => $evenement?->courses()->orderBy('heure_depart')->get() ?? collect(),
        ]);
    }

    /** Liste d'émargement imprimable : une page par course, par ordre alphabétique. */
    public function emargement(Request $request): View
    {
        $evenement = EvenementAdmin::choisi($request);
        abort_unless($evenement, 404);

        $courses = $evenement->courses()->orderBy('heure_depart')
            ->when($request->filled('course'), fn ($q) => $q->whereKey($request->integer('course')))
            ->get();
        $this->journal->noter('export.emargement', $evenement, array_filter(['course' => $courses->count() === 1 ? $courses->first()->nom : null]));

        return view('admin.exports.emargement', [
            'evenement' => $evenement,
            'courses' => $courses->map(fn (Course $c) => [
                'course' => $c,
                'inscrits' => Inscription::with('participant.user')
                    ->whereIn('id_tarif', $c->tarifs()->select('id_tarif'))
                    ->whereIn('statut', [Inscription::VALIDEE, Inscription::DOSSIER_INCOMPLET])
                    ->get()
                    ->sortBy(fn ($i) => mb_strtolower($i->participant->user->nom.' '.$i->participant->user->prenom))
                    ->values(),
            ]),
        ]);
    }

    /** Retraits : chaque inscription validée, remise ou non, avec qui l'a remise et quand. */
    public function retraits(Request $request): Response
    {
        $evenement = EvenementAdmin::choisi($request);
        abort_unless($evenement, 404);
        $this->journal->noter('export.retraits', $evenement);

        $remises = RemiseDossard::where('action', RemiseDossard::REMISE)
            ->whereIn('id_inscription', Inscription::where('id_evenement', $evenement->id_evenement)->select('id_inscription'))
            ->orderBy('id_remise')
            ->get()
            ->keyBy('id_inscription');

        $lignes = function () use ($evenement, $remises) {
            foreach (Inscription::with('participant.user', 'tarif.course')->where('id_evenement', $evenement->id_evenement)->where('statut', Inscription::VALIDEE)->orderBy('dossard')->lazy() as $i) {
                $remise = $i->date_retrait ? $remises->get($i->id_inscription) : null;
                yield [
                    $i->dossard,
                    $i->participant->user->nom,
                    $i->participant->user->prenom,
                    $i->tarif->course->nom,
                    $i->date_retrait ? 'oui' : 'non',
                    $remise?->created_at->format('d/m/Y H:i') ?? ($i->date_retrait?->format('d/m/Y') ?? ''),
                    $remise?->par ?? '',
                ];
            }
        };

        return Csv::telecharger("retraits-{$evenement->id_evenement}-".now()->format('Ymd'), ['Dossard', 'Nom', 'Prénom', 'Course', 'Retiré', 'Le', 'Par'], $lignes());
    }
}
