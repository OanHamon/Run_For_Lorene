<?php

namespace App\Services\Paiement;

use App\Models\Paiement;
use App\Models\Remboursement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Remboursements (spec) : demandés depuis l'admin via l'API du prestataire, ou faits dans le
 * tableau de bord Stripe et reçus par le webhook charge.refunded. Chaque remboursement est
 * enregistré une seule fois, par sa référence (idempotence).
 */
class Rembourser
{
    public function __construct(private Passerelle $passerelle) {}

    /**
     * Remboursement total ou partiel demandé par un admin.
     *
     * @throws ValidationException (clé « montant »)
     */
    public function __invoke(Paiement $paiement, float $montant, ?string $motif): Remboursement
    {
        return DB::transaction(function () use ($paiement, $montant, $motif) {
            $paiement = Paiement::whereKey($paiement->id_paiement)->lockForUpdate()->first();
            $remboursable = $paiement->remboursable();

            if ($montant <= 0 || $montant > $remboursable + 0.001) {
                throw ValidationException::withMessages(['montant' => 'Le montant doit être compris entre 0,01 € et '.number_format($remboursable, 2, ',', ' ').' €.']);
            }

            try {
                $reference = $this->passerelle->rembourser($paiement, $montant, $motif);
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['montant' => $e->getMessage()]);
            }

            return $this->enregistrer($paiement, $reference, $montant, $motif, Auth::id());
        });
    }

    /** Enregistre un remboursement, sauf s'il l'est déjà (même référence). */
    public function enregistrer(Paiement $paiement, string $reference, float $montant, ?string $motif = null, ?int $idAdmin = null, ?\DateTimeInterface $date = null): Remboursement
    {
        return Remboursement::firstOrCreate(['reference' => $reference], [
            'id_paiement' => $paiement->id_paiement,
            'montant' => $montant,
            'motif' => $motif ? mb_substr($motif, 0, 255) : null,
            'id_admin' => $idAdmin,
            'date_remboursement' => $date ?? now(),
        ]);
    }

    /**
     * Webhook charge.refunded : enregistre les remboursements du paiement qui ne le sont pas
     * encore (ceux faits depuis le tableau de bord Stripe, ou si la réponse de l'API s'est perdue).
     */
    public function depuisStripe(object $charge, PasserelleStripe $stripe): void
    {
        $remboursements = collect($stripe->remboursementsDe($charge->id))
            ->filter(fn ($r) => in_array($r->status, ['succeeded', 'pending'], true));

        if ($remboursements->isEmpty()) {
            return;
        }

        $idPaiement = $remboursements->map(fn ($r) => $r->metadata->id_paiement ?? null)->filter()->first();
        $paiement = $idPaiement ? Paiement::find($idPaiement) : null;

        if (! $paiement && $charge->payment_intent) {
            $session = $stripe->sessionDuPaiement((string) $charge->payment_intent);
            $paiement = $session ? Paiement::where('reference', $session)->first() : null;
        }

        if (! $paiement) {
            Log::warning("Remboursement Stripe reçu pour un paiement inconnu (charge {$charge->id}).");

            return;
        }

        foreach ($remboursements as $r) {
            $this->enregistrer($paiement, $r->id, $r->amount / 100, $r->metadata->motif ?? 'Remboursement Stripe', null, now()->setTimestamp($r->created));
        }
    }
}
