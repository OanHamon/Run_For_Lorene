<?php

namespace App\Http\Controllers;

use App\Models\Evenement;
use Illuminate\View\View;

/** Page publique de l'événement (écran 01). */
class AccueilController extends Controller
{
    public function __invoke(): View
    {
        $evenement = Evenement::courant();

        return view('site.evenement', [
            'evenement' => $evenement,
            'ouvert' => $evenement?->inscriptionsOuvertes() ?? false,
            'courses' => $evenement?->courses()->with('tarifs')->orderBy('heure_depart')->get() ?? collect(),
        ]);
    }
}
