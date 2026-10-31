<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Remboursement;
use App\Services\Admin\Statistiques;
use App\Support\EvenementAdmin;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Remboursements de l'événement suivi : depuis l'admin ou directement dans Stripe (webhook). */
class RemboursementController extends Controller
{
    public function index(Request $request): View
    {
        $evenement = EvenementAdmin::choisi($request);

        $remboursements = $evenement
            ? Remboursement::with('paiement.commande.user', 'admin')
                ->whereHas('paiement.commande', fn ($c) => $c->whereIn('id_commande', Statistiques::commandes($evenement)->select('id_commande')))
                ->orderByDesc('date_remboursement')
                ->paginate(50)
            : null;

        return view('admin.remboursements.index', ['evenement' => $evenement, 'remboursements' => $remboursements]);
    }
}
