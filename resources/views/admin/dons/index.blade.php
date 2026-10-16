@php use App\Support\Format; @endphp
<x-admin-shell title="Dons">
    <x-admin.titre titre="Dons" :sous-titre="$evenement?->nom">
        @if ($evenement)<x-admin.filtre-evenement :evenement="$evenement" :evenements="$evenements" />@endif
    </x-admin.titre>

    @if (! $evenement)
        <div class="rfl-carte px-6 py-10 text-center text-gris-texte">Aucun événement.</div>
    @else
        <div class="mb-6 grid gap-4 sm:grid-cols-2">
            <div class="rounded-3xl bg-vert p-6 text-white">
                <p class="text-sm text-vert-clair">{{ request()->boolean('recu') ? 'Dons avec reçu demandé' : 'Total des dons' }}</p>
                <p class="mt-1 font-display text-4xl text-moutarde">{{ Format::euros($total) }}</p>
                <p class="mt-2 text-sm text-vert-clair">{{ $dons->total() }} don(s), dons seuls compris</p>
            </div>
            <div class="rfl-alerte-info text-sm">
                <p class="font-display text-vert">Reçus fiscaux</p>
                <p class="mt-1 text-gris-texte">La génération des reçus en PDF arrive avec les mentions légales de l'association (RNA, adresse, article du CGI). D'ici là, l'export liste les donateurs qui en ont demandé un, avec leur adresse.</p>
            </div>
        </div>

        <div class="rfl-carte overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                <nav class="flex gap-2 text-sm" aria-label="Filtrer les dons">
                    <a href="{{ route('admin.dons.index') }}" @if (! request()->boolean('recu')) aria-current="page" @endif class="rounded-full px-4 py-2 {{ request()->boolean('recu') ? 'ring-1 ring-bordure' : 'bg-vert font-bold text-white' }}">Tous</a>
                    <a href="{{ route('admin.dons.index', ['recu' => 1]) }}" @if (request()->boolean('recu')) aria-current="page" @endif class="rounded-full px-4 py-2 {{ request()->boolean('recu') ? 'bg-vert font-bold text-white' : 'ring-1 ring-bordure' }}">Reçu demandé</a>
                </nav>
                <span class="text-sm">Exporter : <a href="{{ route('admin.dons.export', request()->only('recu')) }}" class="rfl-lien">Excel</a> · <a href="{{ route('admin.dons.export', [...request()->only('recu'), 'format' => 'csv']) }}" class="rfl-lien">CSV</a></span>
            </div>
            <div class="overflow-x-auto">
                <table class="rfl-tableau min-w-[640px]">
                    <thead>
                        <tr><th>Date</th><th>Donateur</th><th class="text-right">Montant</th><th>Affichage</th><th>Reçu fiscal</th><th>Commande</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($dons as $don)
                            <tr>
                                <td class="whitespace-nowrap text-gris-texte">{{ $don->commande->date_commande->format('d/m/Y') }}</td>
                                <td>
                                    <span class="font-bold">{{ $don->commande->user->name }}</span>
                                    <span class="block text-xs text-gris-texte">{{ $don->commande->user->email }}</span>
                                </td>
                                <td class="text-right font-bold">{{ Format::euros($don->montant) }}</td>
                                <td>{{ $don->anonyme ? 'Anonyme' : 'Nom affiché' }}</td>
                                <td>
                                    @if ($don->recu_fiscal_demande)
                                        <span class="rfl-badge bg-moutarde/20 text-vert-sombre">Demandé</span>
                                    @else
                                        <span class="text-gris-texte">–</span>
                                    @endif
                                </td>
                                <td><a href="{{ route('admin.commandes.show', $don->id_commande) }}" class="rfl-lien">n° {{ $don->id_commande }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-gris-texte">Aucun don.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">{{ $dons->links() }}</div>
    @endif
</x-admin-shell>
