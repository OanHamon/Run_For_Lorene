<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\Graphiques;
use App\Support\EvenementAdmin;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Statistiques de l'événement suivi : chaque graphique répond à une question de l'équipe. */
class StatistiquesController extends Controller
{
    public function __invoke(Request $request, Graphiques $graphiques): View
    {
        $evenement = EvenementAdmin::choisi($request);

        return view('admin.statistiques', ['evenement' => $evenement] + ($evenement ? [
            'cumul' => $graphiques->cumul($evenement),
            'remplissage' => $graphiques->remplissage($evenement),
            'entonnoir' => $graphiques->entonnoir($evenement),
            'recettes' => $graphiques->recettes($evenement),
            'repartition' => $graphiques->repartition($evenement),
            'departements' => $graphiques->departements($evenement),
            'moments' => $graphiques->moments($evenement),
        ] : []));
    }
}
