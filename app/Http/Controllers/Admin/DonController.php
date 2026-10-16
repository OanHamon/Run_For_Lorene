<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evenement;
use App\Services\Admin\Statistiques;
use App\Support\Csv;
use App\Support\EvenementAdmin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dons d'un événement (commandes payées) : liste, reçus fiscaux demandés, export CSV.
 * La génération du reçu fiscal en PDF reste à faire (mentions légales de l'association à fournir).
 */
class DonController extends Controller
{
    public function index(Request $request): View
    {
        $evenement = EvenementAdmin::choisi($request);
        $dons = $evenement ? $this->requete($request, $evenement) : null;

        return view('admin.dons.index', [
            'evenement' => $evenement,
            'evenements' => Evenement::orderByDesc('date_evenement')->get(),
            'total' => $dons ? (float) (clone $dons)->sum('montant') : 0,
            'dons' => $dons?->paginate(50)->withQueryString(),
        ]);
    }

    public function export(Request $request): Response
    {
        $evenement = EvenementAdmin::choisi($request);
        abort_unless($evenement, 404);

        $lignes = function () use ($request, $evenement) {
            foreach ($this->requete($request, $evenement)->lazy() as $don) {
                $u = $don->commande->user;
                $p = $u->participant;
                yield [
                    $don->id_don,
                    $don->commande->date_commande->format('d/m/Y'),
                    (float) $don->montant,
                    $u->nom,
                    $u->prenom,
                    $u->email,
                    $p?->adresse,
                    $p?->code_postal,
                    $p?->ville,
                    $don->anonyme ? 'oui' : 'non',
                    $don->recu_fiscal_demande ? 'oui' : 'non',
                    $don->commande->id_commande,
                ];
            }
        };

        return Csv::telecharger("dons-{$evenement->id_evenement}-".now()->format('Ymd'), [
            'N°', 'Date', 'Montant', 'Nom', 'Prénom', 'Email', 'Adresse', 'Code postal', 'Ville', 'Anonyme', 'Reçu fiscal demandé', 'Commande',
        ], $lignes());
    }

    private function requete(Request $request, Evenement $evenement): Builder
    {
        return Statistiques::dons($evenement)
            ->with('commande.user.participant')
            ->when($request->boolean('recu'), fn ($q) => $q->where('recu_fiscal_demande', true))
            ->orderByDesc('id_don');
    }
}
