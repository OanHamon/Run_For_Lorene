<x-tunnel-shell :title="'Paiement (simulation) · Run For Lorène'" :step="7" :total-steps="7" label="Paiement">
    <p class="mb-4 rounded-xl bg-moutarde/15 px-4 py-3 text-sm text-encre">
        <strong>Mode simulation.</strong> Aucune clé Stripe n'est configurée : cette page remplace Stripe Checkout pour tester le parcours. Elle n'existe pas en production.
    </p>

    <h1 class="font-display text-2xl font-bold text-encre">Paiement de {{ number_format((float) $paiement->montant, 2, ',', ' ') }} €</h1>
    <p class="mt-1 mb-6 text-sm text-gris-texte">Commande n° {{ $paiement->commande->id_commande }} · référence {{ $paiement->reference }}</p>

    <div class="space-y-3">
        <form method="POST" action="{{ route('paiement.simuler', [$paiement, 'reussi']) }}">
            @csrf
            <button type="submit" class="rfl-btn w-full">Simuler un paiement réussi</button>
        </form>
        <form method="POST" action="{{ route('paiement.simuler', [$paiement, 'echec']) }}">
            @csrf
            <button type="submit" class="rfl-btn-secondaire w-full">Simuler une carte refusée</button>
        </form>
        <form method="POST" action="{{ route('paiement.simuler', [$paiement, 'abandon']) }}">
            @csrf
            <button type="submit" class="rfl-btn-secondaire w-full">Abandonner (retour du site de paiement)</button>
        </form>
    </div>
</x-tunnel-shell>
