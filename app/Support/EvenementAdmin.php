<?php

namespace App\Support;

use App\Models\Evenement;
use Illuminate\Http\Request;

/**
 * Événement suivi dans le back-office : choisi par le filtre (?evenement=), retenu en session,
 * sinon l'événement en cours côté public, sinon le plus récent.
 */
class EvenementAdmin
{
    public static function choisi(Request $request): ?Evenement
    {
        if ($request->filled('evenement')) {
            $evenement = Evenement::find($request->integer('evenement'));

            if ($evenement) {
                $request->session()->put('admin.evenement', $evenement->id_evenement);

                return $evenement;
            }
        }

        return Evenement::find($request->session()->get('admin.evenement'))
            ?? Evenement::courant()
            ?? Evenement::orderByDesc('date_evenement')->first();
    }
}
