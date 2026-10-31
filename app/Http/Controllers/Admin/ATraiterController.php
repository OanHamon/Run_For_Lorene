<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ATraiter;
use App\Support\EvenementAdmin;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** « À traiter » : une seule liste de tout ce qui attend une action (voir ATraiter). */
class ATraiterController extends Controller
{
    public function __invoke(Request $request, ATraiter $aTraiter): View
    {
        $evenement = EvenementAdmin::choisi($request);

        return view('admin.a-traiter', [
            'evenement' => $evenement,
            'groupes' => $aTraiter->groupes($evenement, $request->user()),
        ]);
    }
}
