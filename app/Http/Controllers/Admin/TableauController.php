<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evenement;
use App\Services\Admin\PreparationEvenement;
use App\Services\Admin\Statistiques;
use App\Support\EvenementAdmin;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tableau de bord admin, limité à l'essentiel : alertes (dossards, ce qui bloque des
 * inscriptions sur un événement publié), inscrits par statut, recettes encaissées (dont
 * dons), remplissage des courses. Le détail est dans les autres rubriques.
 */
class TableauController extends Controller
{
    public function __invoke(Request $request, Statistiques $stats, PreparationEvenement $preparation): View
    {
        $evenement = EvenementAdmin::choisi($request);

        return view('admin.tableau', [
            'evenement' => $evenement,
            'evenements' => Evenement::orderByDesc('date_evenement')->get(),
            'inscrits' => $evenement ? $stats->inscrits($evenement) : null,
            'recettes' => $evenement ? $stats->recettes($evenement) : null,
            'courses' => $evenement ? $stats->courses($evenement) : collect(),
            'paiementsAVerifier' => Statistiques::paiementsAVerifier(),
            // En ligne mais incomplet (trou de tarif, texte manquant) : des personnes ne peuvent pas s'inscrire.
            'bloquants' => $evenement?->statut === Evenement::PUBLIE && $evenement->fermeture->isFuture()
                ? $preparation->problemes($evenement)
                : [],
        ]);
    }
}
