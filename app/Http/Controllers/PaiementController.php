<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Paiement;
use App\Services\Paiement\AbandonnerCommande;
use App\Services\Paiement\DemarrerPaiement;
use App\Services\Paiement\EnregistrerPaiement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Écrans 09 (paiement), 09b (paiement non abouti) et 10 (confirmation). */
class PaiementController extends Controller
{
    public function payer(Request $request, Commande $commande, DemarrerPaiement $demarrer): RedirectResponse
    {
        $this->autoriser($request, $commande);

        if ($commande->statut === Commande::PAYEE) {
            return redirect()->route('inscription.confirmation', $commande);
        }

        try {
            return redirect()->away($demarrer($commande));
        } catch (ValidationException $e) {
            return redirect()->route('inscription.echec', $commande)->with('erreur', $e->validator->errors()->first());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('inscription.echec', $commande)
                ->with('erreur', 'Le service de paiement ne répond pas. Réessaie dans un instant.');
        }
    }

    public function confirmation(Request $request, Commande $commande): View
    {
        $this->autoriser($request, $commande);

        $commande->load('inscriptions.participant.user', 'inscriptions.tarif.course', 'inscriptions.evenement', 'don');

        return view('inscription.confirmation', [
            'commande' => $commande,
            'enAttente' => $commande->statut === Commande::EN_COURS,
        ]);
    }

    public function echec(Request $request, Commande $commande): View|RedirectResponse
    {
        $this->autoriser($request, $commande);

        if ($commande->statut === Commande::PAYEE) {
            return redirect()->route('inscription.confirmation', $commande);
        }

        return view('inscription.paiement-echec', ['commande' => $commande]);
    }

    public function modifier(Request $request, Commande $commande, AbandonnerCommande $abandonner): RedirectResponse
    {
        $this->autoriser($request, $commande);
        $don = $commande->don; // lu avant l'abandon, qui supprime le don de la commande

        if (! $abandonner($commande)) {
            return redirect()->route('espace')
                ->with('erreur', 'Cette commande ne peut plus être modifiée : un paiement est peut-être en cours de validation.');
        }

        if ($don) {
            session(['tunnel.don' => (string) (float) $don->montant]);
        }

        return redirect()->route('inscription.tunnel');
    }

    // ------------------------------------------------------------------
    // Simulation (APP_ENV local ou testing uniquement, sans clé Stripe)
    // ------------------------------------------------------------------

    public function simulation(Request $request, Paiement $paiement): View
    {
        $this->autoriserSimulation($request, $paiement);

        return view('inscription.simulation', ['paiement' => $paiement->load('commande')]);
    }

    public function simuler(Request $request, Paiement $paiement, string $issue, EnregistrerPaiement $enregistrer): RedirectResponse
    {
        $this->autoriserSimulation($request, $paiement);
        $commande = $paiement->commande;

        return match ($issue) {
            'reussi' => tap(redirect()->route('inscription.confirmation', $commande), fn () => $enregistrer->reussi($paiement->reference, (float) $paiement->montant, 0)),
            'echec' => tap(redirect()->route('inscription.echec', $commande)->with('erreur', 'Ta banque a refusé le paiement.'), fn () => $enregistrer->echoue($paiement->reference)),
            default => redirect()->route('inscription.echec', $commande),
        };
    }

    private function autoriser(Request $request, Commande $commande): void
    {
        abort_unless($commande->id_user === $request->user()->id_user, 404);
    }

    private function autoriserSimulation(Request $request, Paiement $paiement): void
    {
        abort_if(! app()->environment('local', 'testing') || config('paiement.passerelle') !== 'simulation', 404);
        $this->autoriser($request, $paiement->commande);
        abort_unless($paiement->statut === Paiement::EN_ATTENTE, 410, 'Cette tentative de paiement est terminée.');

        Log::debug("Simulation de paiement : {$paiement->reference}");
    }
}
