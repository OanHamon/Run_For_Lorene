@php($enCours = $commande->statut === \App\Models\Commande::EN_COURS)
<x-tunnel-shell :title="'Paiement non abouti · Run For Lorène'" :step="7" :total-steps="7" label="Paiement">
    <div class="py-2">
        <div class="mb-6 flex h-14 w-14 items-center justify-center rounded-full bg-moutarde/20">
            <svg class="h-7 w-7 text-vert-sombre" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 8v5m0 3.5v.01M12 3a9 9 0 1 1 0 18 9 9 0 0 1 0-18Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
        </div>

        <h1 class="font-display text-2xl text-vert">Le paiement n'a pas abouti</h1>

        @if (session('erreur'))
            <p class="mt-4 rfl-alerte-erreur" role="alert">{{ session('erreur') }}</p>
        @endif

        {{-- Inscription d'une traite : l'inscription n'est enregistrée qu'une fois payée. --}}
        @if ($enCours)
            {{-- Pas de « rien n'a été débité » sous un message d'erreur (ex. paiement déjà en cours). --}}
            @unless (session('erreur'))
                <p class="mt-3 text-sm text-gris-texte">
                    Rien n'a été débité. Ton inscription n'est enregistrée qu'une fois payée : règle-la maintenant pour garder ta place.
                </p>
            @endunless

            <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                <form method="POST" action="{{ route('inscription.payer', $commande) }}" data-envoi-unique>
                    @csrf
                    <button type="submit" class="rfl-btn w-full sm:w-auto">Réessayer · {{ \App\Support\Format::euros($commande->montant_total) }}</button>
                </form>
            </div>

            <form method="POST" action="{{ route('inscription.modifier', $commande) }}" class="mt-6 border-t border-bordure pt-4 text-center">
                @csrf
                <button type="submit" class="text-sm rfl-lien">Modifier ma commande (épreuve, participants, don)</button>
            </form>
        @else
            <p class="mt-3 text-sm text-gris-texte">
                Rien n'a été débité et ton inscription n'est pas enregistrée : aucune place n'est gardée.
                Tes informations sont conservées, il te reste à repasser par le récapitulatif et le paiement.
            </p>

            <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
                <a href="{{ route('espace') }}" class="text-sm rfl-lien text-center">Aller à mon espace</a>
                <a href="{{ route('inscription.tunnel') }}" class="rfl-btn w-full sm:w-auto">Reprendre mon inscription</a>
            </div>
        @endif
    </div>
</x-tunnel-shell>
