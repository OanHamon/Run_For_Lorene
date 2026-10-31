<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Commande;
use App\Models\Inscription;
use App\Models\User;
use App\Support\EvenementAdmin;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Recherche globale de la barre latérale : par nom ou email (inscriptions de l'événement
 * suivi, comptes), par numéro de dossard ou de commande.
 */
class RechercheController extends Controller
{
    public const MAX = 20;

    public function __invoke(Request $request): View
    {
        $terme = trim((string) $request->query('q'));
        $evenement = EvenementAdmin::choisi($request);
        $resultats = ['inscriptions' => collect(), 'comptes' => collect(), 'commandes' => collect()];

        if (mb_strlen($terme) >= 2 || ctype_digit($terme)) {
            $nombre = ctype_digit($terme) ? (int) $terme : null;
            $nom = fn ($q) => $q->whereRaw("concat(prenom, ' ', nom) like ?", ["%{$terme}%"])->orWhereRaw("concat(nom, ' ', prenom) like ?", ["%{$terme}%"]);

            $resultats['inscriptions'] = Inscription::with('participant.user', 'tarif.course', 'evenement')
                ->where('statut', '!=', Inscription::BROUILLON)
                ->when($evenement, fn ($q) => $q->where('id_evenement', $evenement->id_evenement))
                ->where(function ($q) use ($nombre, $nom, $terme) {
                    $nombre !== null
                        ? $q->where('dossard', $nombre)
                        : $q->whereHas('participant.user', fn ($u) => $nom($u)->orWhere('email', 'like', "%{$terme}%"));
                })
                ->orderByDesc('id_inscription')
                ->limit(self::MAX)
                ->get();

            if ($request->user()->peutAdmin(Admin::SUPER) && $nombre === null) {
                $resultats['comptes'] = User::with('admin')
                    ->whereNotNull('email')
                    ->where(fn ($q) => $nom($q)->orWhere('email', 'like', "%{$terme}%"))
                    ->orderBy('nom')
                    ->limit(self::MAX)
                    ->get();
            }

            if ($nombre !== null) {
                $resultats['commandes'] = Commande::with('user')->whereKey($nombre)->get();
            }
        }

        return view('admin.recherche', ['terme' => $terme, 'evenement' => $evenement] + $resultats);
    }
}
