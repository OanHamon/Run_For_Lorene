<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inscription;
use App\Models\PosteRetrait;
use App\Services\Admin\Journal;
use App\Services\Retrait\Retraits;
use App\Support\EvenementAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Retrait des dossards côté admin (niveau 2) : même écran que les postes des bénévoles, plus
 * la gestion de ces postes (lien à partager, dates de validité, révocation).
 */
class RetraitController extends Controller
{
    public function __construct(private Retraits $retraits, private Journal $journal) {}

    public function index(Request $request): View
    {
        $evenement = EvenementAdmin::choisi($request);
        abort_unless($evenement, 404);

        return view('admin.retrait.index', [
            'evenement' => $evenement,
            'ecran' => $this->ecran($request, $evenement),
            'postes' => PosteRetrait::with('admin')->withCount(['remises' => fn ($q) => $q->where('action', 'remise')])
                ->where('id_evenement', $evenement->id_evenement)->orderByDesc('id_poste')->get(),
        ]);
    }

    public function scan(Request $request): RedirectResponse
    {
        $evenement = EvenementAdmin::choisi($request);
        $resultat = $this->retraits->parBillet($evenement, (string) $request->input('jeton'));

        return $resultat['inscription']
            ? redirect()->route('admin.retrait.index')->with('retrait.trouve', $resultat['inscription']->id_inscription)
            : redirect()->route('admin.retrait.index')->with('retrait.erreur', $resultat['erreur']);
    }

    public function remettre(Request $request, Inscription $inscription): RedirectResponse
    {
        $inscription = $this->retraits->trouver(EvenementAdmin::choisi($request), $inscription->id_inscription) ?? abort(404);
        $resultat = $this->retraits->remettre($inscription, $request->user()->name, admin: $request->user());

        return $this->retour($resultat, $inscription);
    }

    public function annuler(Request $request, Inscription $inscription): RedirectResponse
    {
        $inscription = $this->retraits->trouver(EvenementAdmin::choisi($request), $inscription->id_inscription) ?? abort(404);
        $resultat = $this->retraits->annuler($inscription, $request->user()->name, admin: $request->user());
        if ($resultat['ok']) {
            $this->journal->noter('inscription.retrait_annule', $inscription, ['dossard' => $inscription->dossard]);
        }

        return $this->retour($resultat, $inscription);
    }

    /** Nouveau poste : le lien n'est affiché qu'une fois, à partager avec les bénévoles du poste. */
    public function ouvrirPoste(Request $request): RedirectResponse
    {
        $evenement = EvenementAdmin::choisi($request);
        $donnees = $request->validateWithBag('poste', [
            'libelle' => ['required', 'string', 'max:60'],
            'valable_du' => ['required', 'date'],
            'valable_au' => ['required', 'date', 'after_or_equal:valable_du'],
        ], [
            'libelle.required' => 'Donne un nom au poste (« Entrée », « Tente 2 »…).',
            'valable_au.after_or_equal' => 'La fin doit être le même jour ou après le début.',
        ]);

        [$poste, $jeton] = PosteRetrait::ouvrir($donnees + ['id_evenement' => $evenement->id_evenement, 'id_admin' => $request->user()->id_user]);
        $this->journal->noter('retrait.poste', $poste, ['poste' => $poste->libelle, 'du' => $poste->valable_du->format('d/m/Y'), 'au' => $poste->valable_au->format('d/m/Y')]);

        return redirect()->route('admin.retrait.index')
            ->with('retrait.lien', ['poste' => $poste->libelle, 'url' => route('retrait.poste', $jeton)]);
    }

    public function revoquerPoste(PosteRetrait $poste): RedirectResponse
    {
        if (! $poste->revoque_le) {
            $poste->forceFill(['revoque_le' => now()])->save();
            $this->journal->noter('retrait.revocation', $poste, ['poste' => $poste->libelle]);
        }

        return back()->with('statut', "Poste « {$poste->libelle} » fermé : son lien ne fonctionne plus.");
    }

    private function retour(array $resultat, Inscription $inscription): RedirectResponse
    {
        return redirect()->route('admin.retrait.index')
            ->with('retrait.trouve', $inscription->id_inscription)
            ->with($resultat['ok'] ? 'retrait.succes' : 'retrait.erreur', $resultat['message']);
    }

    private function ecran(Request $request, $evenement): array
    {
        return [
            'evenement' => $evenement,
            'compteur' => $this->retraits->compteur($evenement),
            'terme' => (string) $request->query('q'),
            'resultats' => $this->retraits->rechercher($evenement, (string) $request->query('q')),
            'trouve' => session('retrait.trouve') ? $this->retraits->trouver($evenement, (int) session('retrait.trouve')) : null,
            'routes' => [
                'recherche' => route('admin.retrait.index'),
                'scan' => route('admin.retrait.scan'),
                'remettre' => fn (Inscription $i) => route('admin.retrait.remettre', $i),
                'annuler' => fn (Inscription $i) => route('admin.retrait.annuler', $i),
            ],
        ];
    }
}
