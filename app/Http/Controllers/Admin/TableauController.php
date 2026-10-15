<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evenement;
use App\Services\Admin\Statistiques;
use App\Support\EvenementAdmin;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tableau de bord admin, limité à l'essentiel : alertes dossards, inscrits par statut, recettes
 * encaissées (dont dons), remplissage des courses. Le détail est dans les autres rubriques.
 */
class TableauController extends Controller
{
    public function __invoke(Request $request, Statistiques $stats): View
    {
        $evenement = EvenementAdmin::choisi($request);

        return view('admin.tableau', [
            'evenement' => $evenement,
            'evenements' => Evenement::orderByDesc('date_evenement')->get(),
            'inscrits' => $evenement ? $stats->inscrits($evenement) : null,
            'recettes' => $evenement ? $stats->recettes($evenement) : null,
            'courses' => $evenement ? $stats->courses($evenement) : collect(),
        ]);
    }
}
