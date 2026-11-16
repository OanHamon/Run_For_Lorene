<?php

namespace App\Http\Controllers;

use App\Models\CandidatureBenevole;
use App\Models\Evenement;
use App\Support\Benevolat;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Page publique « Devenir bénévole » : l'événement, les missions, puis la candidature. */
class BenevolesController extends Controller
{
    public function __invoke(Request $request): View
    {
        $evenement = Evenement::courant();

        $candidature = $request->user() ? CandidatureBenevole::duCompte($request->user()) : null;

        return view('site.benevoles', [
            'evenement' => $evenement,
            'ouvert' => $evenement !== null && Benevolat::ouvert(),
            'candidature' => $candidature,
        ]);
    }
}
