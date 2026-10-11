<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evenement;
use App\Services\Admin\Statistiques;
use App\Support\EvenementAdmin;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Tableau de bord admin : inscrits, recettes, t-shirts, remplissage des courses, par événement. */
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
            'tshirts' => $evenement ? $stats->tshirts($evenement) : collect(),
            'courses' => $evenement ? $stats->courses($evenement) : collect(),
        ]);
    }
}
