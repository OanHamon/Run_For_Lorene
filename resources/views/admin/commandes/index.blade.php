@php
    use App\Http\Controllers\Admin\CommandeController;
    use App\Models\Commande;
    use App\Support\Format;
@endphp
<x-admin-shell title="Commandes et paiements">
    <x-admin.titre titre="Commandes et paiements" :sous-titre="$evenement?->nom">
        @if ($evenement)<x-admin.filtre-evenement :evenement="$evenement" :evenements="$evenements" />@endif
    </x-admin.titre>

    @if (! $evenement)
        <div class="rfl-carte px-6 py-10 text-center text-gris-texte">Aucun événement.</div>
    @else
        <form method="GET" class="rfl-carte mb-6 grid gap-4 p-5 sm:grid-cols-[2fr_1fr_auto]" role="search">
            <div>
                <label for="q" class="rfl-label">Rechercher</label>
                <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="N° de commande, payeur, référence Stripe" class="rfl-champ-compact">
            </div>
            <div>
                <label for="statut" class="rfl-label">Statut</label>
                <select id="statut" name="statut" class="rfl-champ-compact">
                    <option value="">Tous</option>
                    @foreach (CommandeController::LIBELLES as $valeur => $libelle)
                        <option value="{{ $valeur }}" @selected($statut === $valeur)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end"><button type="submit" class="rfl-btn px-5 py-2.5 text-sm">Filtrer</button></div>
        </form>

        <div class="rfl-carte overflow-hidden">
            <div class="overflow-x-auto">
                <table class="rfl-tableau min-w-[820px]">
                    <thead>
                        <tr><th>N°</th><th>Date</th><th>Payeur</th><th>Contenu</th><th>Statut</th><th class="text-right">Montant</th><th class="text-right">Frais</th><th class="text-right">Remboursé</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($commandes as $c)
                            @php
                                $rembourse = $c->paiements->flatMap->remboursements->sum('montant');
                                $frais = $c->paiements->where('statut', 'reussi')->sum('frais');
                            @endphp
                            <tr class="relative hover:bg-fond/60">
                                <td class="font-bold"><a href="{{ route('admin.commandes.show', $c) }}" class="after:absolute after:inset-0">{{ $c->id_commande }}</a></td>
                                <td class="whitespace-nowrap text-gris-texte">{{ $c->date_commande->format('d/m/Y H:i') }}</td>
                                <td>{{ $c->user->name }}</td>
                                <td class="text-gris-texte">
                                    {{ $c->inscriptions_count }} inscription(s)@if ($c->don) · don {{ Format::euros($c->don->montant) }}@endif
                                </td>
                                <td>
                                    <span class="rfl-badge {{ match ($c->statut) { Commande::PAYEE => 'bg-vert-clair text-vert', Commande::EN_COURS => 'bg-moutarde/20 text-vert-sombre', default => 'bg-bordure text-gris-texte' } }}">{{ CommandeController::LIBELLES[$c->statut] ?? $c->statut }}</span>
                                </td>
                                <td class="text-right font-bold">{{ Format::euros($c->montant_total) }}</td>
                                <td class="text-right text-gris-texte">{{ $frais > 0 ? Format::euros($frais) : '–' }}</td>
                                <td class="text-right">{{ $rembourse > 0 ? Format::euros($rembourse) : '–' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-gris-texte">Aucune commande.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">{{ $commandes->links() }}</div>
    @endif
</x-admin-shell>
