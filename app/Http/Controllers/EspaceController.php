<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Don;
use App\Models\Evenement;
use App\Models\Inscription;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Espace participant (écran 11) : tableau de bord du compte et de ses participants gérés. */
class EspaceController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $evenement = Evenement::courant();

        $idsParticipants = Participant::where('id_user', $user->id_user)
            ->orWhere('id_responsable', $user->id_user)
            ->pluck('id_user');

        $inscriptions = Inscription::with('participant.user', 'tarif.course', 'commande', 'evenement')
            ->whereIn('id_participant', $idsParticipants)
            ->orderByDesc('id_evenement')
            ->orderBy('id_inscription')
            ->get();

        $brouillons = $evenement
            ? Inscription::whereIn('id_participant', $idsParticipants)
                ->where('id_evenement', $evenement->id_evenement)
                ->where('statut', Inscription::BROUILLON)
                ->count()
            : 0;

        $commandesEnAttente = Commande::with('inscriptions.participant.user', 'inscriptions.tarif.course', 'don')
            ->where('id_user', $user->id_user)
            ->where('statut', Commande::EN_COURS)
            ->orderByDesc('id_commande')
            ->get();

        $dons = Don::with('commande')
            ->whereHas('commande', fn ($q) => $q->where('id_user', $user->id_user)->where('statut', Commande::PAYEE))
            ->orderByDesc('id_don')
            ->get();

        return view('site.espace', [
            'user' => $user,
            'evenement' => $evenement,
            'inscriptions' => $inscriptions,
            'brouillons' => $brouillons,
            'commandesEnAttente' => $commandesEnAttente,
            'dons' => $dons,
            'incompletes' => $inscriptions->where('statut', Inscription::DOSSIER_INCOMPLET),
        ]);
    }
}
