<x-tunnel-shell :title="'Paiement non abouti · Run For Lorène'" :step="7" :total-steps="7" label="Paiement">
    <div class="py-2">
        <div class="mb-6 flex h-14 w-14 items-center justify-center rounded-full bg-moutarde/20">
            <svg class="h-7 w-7 text-encre" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 8v5m0 3.5v.01M12 3a9 9 0 1 1 0 18 9 9 0 0 1 0-18Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
        </div>

        <h1 class="font-display text-2xl font-bold text-encre">Le paiement n'a pas abouti</h1>

        @if (session('erreur'))
            <p class="mt-4 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">{{ session('erreur') }}</p>
        @endif

        <p class="mt-3 text-sm text-gris-texte">
            Pas d'inquiétude : rien n'a été débité et ta commande est gardée telle quelle, tu n'as rien à ressaisir.
        </p>

        <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
            @if ($commande->statut === \App\Models\Commande::EN_COURS)
                <form method="POST" action="{{ route('inscription.payer', $commande) }}">
                    @csrf
                    <button type="submit" class="rfl-btn w-full sm:w-auto">Réessayer · {{ number_format((float) $commande->montant_total, fmod((float) $commande->montant_total, 1.0) == 0.0 ? 0 : 2, ',', ' ') }} €</button>
                </form>
            @endif
        </div>

        @if ($commande->statut === \App\Models\Commande::EN_COURS)
            <form method="POST" action="{{ route('inscription.modifier', $commande) }}" class="mt-6 border-t border-bordure pt-4 text-center">
                @csrf
                <button type="submit" class="text-sm rfl-lien">Modifier ma commande (épreuve, participants, don)</button>
            </form>
        @else
            <a href="{{ route('espace') }}" class="rfl-lien">Aller à mon espace</a>
        @endif
    </div>
</x-tunnel-shell>
