<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\Evenement;
use App\Models\Paiement;
use App\Services\Admin\Journal;
use App\Services\Admin\Statistiques;
use App\Services\Inscriptions\ConfirmerCommande;
use App\Services\Paiement\Rembourser;
use App\Support\EvenementAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Commandes et paiements : tentatives, frais Stripe, remboursements (niveau 3). */
class CommandeController extends Controller
{
    public const LIBELLES = [
        Commande::EN_COURS => 'En attente de paiement',
        Commande::PAYEE => 'Payée',
        Commande::ABANDONNEE => 'Abandonnée',
        Commande::GRATUITE => 'Gratuite',
    ];

    public function index(Request $request): View
    {
        $evenement = EvenementAdmin::choisi($request);
        $statut = array_key_exists((string) $request->query('statut'), self::LIBELLES) ? $request->query('statut') : null;

        $commandes = $evenement
            ? Statistiques::commandes($evenement)
                ->with('user', 'don', 'paiements.remboursements')
                ->withCount('inscriptions')
                ->when($statut, fn ($q) => $q->where('statut', $statut))
                ->when($request->filled('q'), function ($q) use ($request) {
                    $terme = trim((string) $request->query('q'));
                    $q->where(fn ($r) => $r->when(ctype_digit($terme), fn ($n) => $n->where('id_commande', (int) $terme))
                        ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$terme}%")->orWhereRaw("concat(prenom, ' ', nom) like ?", ["%{$terme}%"]))
                        ->orWhereHas('paiements', fn ($p) => $p->where('reference', $terme)));
                })
                ->orderByDesc('id_commande')
                ->paginate(50)
                ->withQueryString()
            : null;

        return view('admin.commandes.index', [
            'evenement' => $evenement,
            'evenements' => Evenement::orderByDesc('date_evenement')->get(),
            'commandes' => $commandes,
            'statut' => $statut,
        ]);
    }

    public function show(Commande $commande): View
    {
        $commande->load('user', 'don', 'inscriptions.participant.user', 'inscriptions.tarif.course', 'inscriptions.declinaisons.declinaison.optionEvenement', 'paiements.remboursements.admin');

        return view('admin.commandes.show', [
            'commande' => $commande,
            'stripeTest' => str_starts_with((string) config('services.stripe.secret'), 'sk_test'),
        ]);
    }

    /**
     * Commande restée « en attente » alors qu'un paiement réussi la couvre (confirmation
     * interrompue et webhook plus renvoyé par Stripe) : un super admin la confirme à la main.
     */
    public function confirmer(Commande $commande, ConfirmerCommande $confirmer, Journal $journal): RedirectResponse
    {
        $paiement = $commande->paiements()->where('statut', Paiement::REUSSI)->get()->first(fn (Paiement $p) => Statistiques::couvre($p));

        if ($commande->statut !== Commande::EN_COURS || ! $paiement) {
            return back()->with('erreur', 'Cette commande ne peut pas être confirmée : aucun paiement réussi ne couvre son total, ou elle n\'est plus en attente.');
        }

        $confirmer($commande);
        $journal->noter('commande.confirmation', $commande, ['paiement' => $paiement->reference]);

        return back()->with('statut', 'Commande confirmée : les inscriptions sont validées (ou en dossier incomplet) et le payeur reçoit son récapitulatif.');
    }

    public function rembourser(Request $request, Paiement $paiement, Rembourser $rembourser, Journal $journal): RedirectResponse
    {
        $donnees = $request->validate([
            'montant' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'motif' => ['nullable', 'string', 'max:255'],
        ]);

        $remboursement = $rembourser($paiement, (float) $donnees['montant'], $donnees['motif'] ?? null);

        $journal->noter('paiement.remboursement', $paiement->commande, [
            'paiement' => $paiement->reference,
            'montant' => (string) $remboursement->montant,
            'motif' => $remboursement->motif,
        ]);

        return back()->with('statut', 'Remboursement de '.number_format((float) $remboursement->montant, 2, ',', ' ').' € envoyé. Les inscriptions ne sont pas annulées pour autant : annule-les depuis leur fiche si besoin.');
    }
}
