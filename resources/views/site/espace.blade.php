@php
    use App\Models\Inscription;

    $euros = fn ($m) => number_format((float) $m, fmod((float) $m, 1.0) == 0.0 ? 0 : 2, ',', ' ').' €';
    $badges = [
        Inscription::VALIDEE => 'bg-aqua-clair text-canard',
        Inscription::DOSSIER_INCOMPLET => 'bg-moutarde/20 text-encre',
        Inscription::EN_ATTENTE_PAIEMENT => 'bg-moutarde/20 text-encre',
        Inscription::ANNULEE => 'bg-bordure text-gris-texte',
        Inscription::BROUILLON => 'bg-bordure text-gris-texte',
    ];
    $joursRestants = $evenement ? (int) today()->diffInDays($evenement->date_evenement, false) : null;
    $inscriptionsEvenement = $evenement ? $inscriptions->where('id_evenement', $evenement->id_evenement) : collect();
    $ouvert = $evenement?->inscriptionsOuvertes();
@endphp
<x-espace-shell title="Mon espace">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="font-display text-3xl font-bold text-encre">Bonjour {{ $user->prenom }}</h1>
                    @if ($evenement)
                        <p class="mt-1 text-sm text-gris-texte">{{ $evenement->nom }} &middot; {{ $evenement->date_evenement->translatedFormat('l j F Y') }} &middot; Nantes</p>
                    @endif
                </div>
                @if ($ouvert)
                    <a href="{{ route('inscription.tunnel', ['nouveau' => 1]) }}" class="rounded-lg bg-canard px-4 py-2 text-sm font-medium text-white hover:bg-profond">Inscrire quelqu'un</a>
                @endif
            </div>

            @if (session('erreur'))
                <p class="mb-6 rfl-alerte-erreur" role="alert">{{ session('erreur') }}</p>
            @endif
            @if (session('statut'))
                <p class="mb-6 rfl-alerte-info text-sm text-canard" role="status">{{ session('statut') }}</p>
            @endif

            {{-- Paiements en attente --}}
            @foreach ($commandesEnAttente as $commande)
                <div class="mb-6 rfl-alerte">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="font-display font-bold text-encre">Action requise : paiement en attente ({{ $euros($commande->montant_total) }})</p>
                            <p class="mt-1 text-sm text-gris-texte">
                                {{ $commande->inscriptions->map(fn ($i) => $i->participant->user->prenom.' · '.$i->tarif->course->nom)->join(', ') ?: 'Don' }}.
                                La place n'est garantie qu'une fois le paiement reçu.
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-3">
                            <form method="POST" action="{{ route('inscription.modifier', $commande) }}">
                                @csrf
                                <button type="submit" class="rounded-lg border border-bordure bg-white px-4 py-2 text-sm font-medium text-encre hover:border-canard">Modifier</button>
                            </form>
                            <form method="POST" action="{{ route('inscription.payer', $commande) }}">
                                @csrf
                                <button type="submit" class="rounded-lg bg-canard px-4 py-2 text-sm font-medium text-white hover:bg-profond">Payer {{ $euros($commande->montant_total) }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- Brouillon en cours --}}
            @if ($brouillons > 0 && $ouvert && $commandesEnAttente->isEmpty())
                <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-lg border border-bordure bg-white px-5 py-4">
                    <div>
                        <p class="font-display font-bold text-encre">Inscription non terminée</p>
                        <p class="mt-1 text-sm text-gris-texte">{{ $brouillons > 1 ? "{$brouillons} inscriptions t'attendent" : 'Une inscription t\'attend' }} : reprends là où tu t'étais arrêté(e).</p>
                    </div>
                    <a href="{{ route('inscription.tunnel') }}" class="shrink-0 rounded-lg bg-canard px-4 py-2 text-sm font-medium text-white hover:bg-profond">Reprendre</a>
                </div>
            @endif

            @if ($incompletes->isNotEmpty())
                <div class="mb-6 rfl-alerte">
                    <p class="font-display font-bold text-encre">Action requise : questionnaire de santé</p>
                    <p class="mt-1 text-sm text-gris-texte">
                        Le dossier de {{ $incompletes->map(fn ($i) => $i->participant->user->prenom)->join(', ', ' et ') }} sera validé dès que le questionnaire de santé sera complété{{ $evenement ? ', avant le '.$evenement->date_evenement->copy()->subDays(15)->translatedFormat('j F Y') : '' }}.
                        Le formulaire en ligne arrive très bientôt dans ton espace.
                    </p>
                </div>
            @endif

            @if ($evenement)
                <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <div class="rounded-lg border border-bordure bg-white p-4">
                        <p class="text-xs font-semibold tracking-wide text-gris-texte uppercase">Inscriptions</p>
                        <p class="mt-1 font-display text-xl font-bold text-encre">{{ $inscriptionsEvenement->whereIn('statut', [Inscription::VALIDEE, Inscription::DOSSIER_INCOMPLET])->count() }}</p>
                        <p class="text-sm text-gris-texte">pour {{ $evenement->nom }}</p>
                    </div>
                    <div class="rounded-lg border border-bordure bg-white p-4">
                        <p class="text-xs font-semibold tracking-wide text-gris-texte uppercase">Dossiers validés</p>
                        <p class="mt-1 font-display text-xl font-bold text-encre">{{ $inscriptionsEvenement->where('statut', Inscription::VALIDEE)->count() }} / {{ $inscriptionsEvenement->whereNotIn('statut', [Inscription::ANNULEE, Inscription::BROUILLON])->count() }}</p>
                        @if ($incompletes->isNotEmpty())
                            <span class="inline-block rounded-full bg-moutarde/20 px-2 py-0.5 text-xs font-semibold text-encre">À compléter</span>
                        @endif
                    </div>
                    <div class="rounded-lg border border-bordure bg-white p-4">
                        <p class="text-xs font-semibold tracking-wide text-gris-texte uppercase">Paiement</p>
                        @if ($commandesEnAttente->isNotEmpty())
                            <p class="mt-1 font-display text-xl font-bold text-encre">{{ $euros($commandesEnAttente->sum('montant_total')) }}</p>
                            <span class="inline-block rounded-full bg-moutarde/20 px-2 py-0.5 text-xs font-semibold text-encre">En attente</span>
                        @else
                            <p class="mt-1 font-display text-xl font-bold text-encre">{{ $euros($inscriptionsEvenement->whereIn('statut', [Inscription::VALIDEE, Inscription::DOSSIER_INCOMPLET])->sum('prix_paye')) }}</p>
                            <span class="inline-block rounded-full bg-aqua-clair px-2 py-0.5 text-xs font-semibold text-canard">Réglé</span>
                        @endif
                    </div>
                    <div class="rounded-lg border border-bordure bg-white p-4">
                        <p class="text-xs font-semibold tracking-wide text-gris-texte uppercase">Jour J</p>
                        <p class="mt-1 font-display text-xl font-bold text-encre">{{ $joursRestants > 0 ? 'J-'.$joursRestants : ($joursRestants === 0 ? "C'est aujourd'hui !" : 'Terminé') }}</p>
                        <p class="text-sm text-gris-texte">{{ $evenement->date_evenement->translatedFormat('j F Y') }}</p>
                    </div>
                </div>
            @endif

            <div class="mb-6 overflow-hidden rounded-lg border border-bordure bg-white">
                <div class="flex items-center justify-between px-6 py-4">
                    <h2 class="font-display font-bold text-encre">Inscriptions</h2>
                    @if ($inscriptions->isNotEmpty())<p class="text-sm text-gris-texte">Clique sur une inscription pour voir où elle en est</p>@endif
                </div>
                @if ($inscriptions->isEmpty())
                    <div class="border-t border-bordure px-6 py-8 text-center text-sm text-gris-texte">
                        Aucune inscription pour l'instant.
                        @if ($ouvert)
                            <a href="{{ route('inscription.tunnel') }}" class="rfl-lien">S'inscrire à {{ $evenement->nom }}</a>
                        @endif
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[560px] text-left text-sm">
                            <thead class="border-y border-bordure bg-creme/60 text-xs font-semibold tracking-wide text-gris-texte uppercase">
                                <tr>
                                    <th class="px-6 py-3">Participant</th>
                                    <th class="px-6 py-3">Épreuve</th>
                                    <th class="px-6 py-3">Statut</th>
                                    <th class="px-6 py-3">Dossard</th>
                                    <th class="px-6 py-3 text-right">Prix</th>
                                    <th class="w-10 px-4 py-3"><span class="sr-only">Détail</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-bordure">
                                @foreach ($inscriptions as $i)
                                    <tr class="relative cursor-pointer hover:bg-creme/60 focus-within:bg-creme/60">
                                        <td class="px-6 py-3 font-medium text-encre">
                                            <a href="{{ route('espace.inscription', $i) }}" class="after:absolute after:inset-0 focus-visible:outline-none">{{ $i->participant->user->prenom }} {{ $i->participant->user->nom }}</a>
                                        </td>
                                        <td class="px-6 py-3 text-encre">
                                            {{ $i->tarif->course->nom }}
                                            @if ($i->id_evenement !== $evenement?->id_evenement)<span class="block text-xs text-gris-texte">{{ $i->evenement->nom }}</span>@endif
                                        </td>
                                        <td class="px-6 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $badges[$i->statut] ?? 'bg-bordure text-gris-texte' }}">{{ $i->libelleStatut() }}</span></td>
                                        <td class="px-6 py-3 text-encre">{{ $i->dossard ?? '–' }}</td>
                                        <td class="px-6 py-3 text-right text-encre">{{ $i->statut === Inscription::BROUILLON ? '–' : $euros($i->prix_paye) }}</td>
                                        <td class="px-4 py-3 text-right text-gris-texte" aria-hidden="true">
                                            <svg class="ml-auto h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="m9 6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            @if ($dons->isNotEmpty())
                <div class="overflow-hidden rounded-lg border border-bordure bg-white">
                    <div class="flex items-center justify-between px-6 py-4">
                        <h2 class="font-display font-bold text-encre">Mes dons</h2>
                        <p class="text-sm font-medium text-encre">Total : {{ $euros($dons->sum('montant')) }}</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[480px] text-left text-sm">
                            <thead class="border-y border-bordure bg-creme/60 text-xs font-semibold tracking-wide text-gris-texte uppercase">
                                <tr>
                                    <th class="px-6 py-3">Date</th>
                                    <th class="px-6 py-3">Montant</th>
                                    <th class="px-6 py-3">Reçu fiscal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-bordure">
                                @foreach ($dons as $don)
                                    <tr>
                                        <td class="px-6 py-3 text-gris-texte">{{ $don->commande->date_commande->translatedFormat('j F Y') }}</td>
                                        <td class="px-6 py-3 font-medium text-encre">{{ $euros($don->montant) }}</td>
                                        <td class="px-6 py-3 text-gris-texte">{{ $don->recu_fiscal_demande ? 'Demandé' : 'Non demandé' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
</x-espace-shell>
