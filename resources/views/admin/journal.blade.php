<x-admin-shell title="Journal">
    <x-admin.titre titre="Journal" sous-titre="Actions sensibles du back-office, avec leur auteur et l'heure." />

    <div class="rfl-carte overflow-hidden">
        <div class="overflow-x-auto">
            <table class="rfl-tableau min-w-[640px]">
                <thead>
                    <tr><th>Date</th><th>Auteur</th><th>Action</th><th>Cible</th><th>Détails</th></tr>
                </thead>
                <tbody>
                    @forelse ($journal as $ligne)
                        <tr>
                            <td class="whitespace-nowrap text-gris-texte">{{ $ligne->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $ligne->auteur->name }}</td>
                            <td class="font-bold">{{ $ligne->libelle() }}</td>
                            <td>
                                @if ($ligne->cible_type === 'inscription' && Route::has('admin.inscriptions.show'))
                                    <a href="{{ route('admin.inscriptions.show', $ligne->cible_id) }}" class="rfl-lien">Inscription n° {{ $ligne->cible_id }}</a>
                                @elseif ($ligne->cible_type)
                                    {{ ucfirst(str_replace('_', ' ', $ligne->cible_type)) }} n° {{ $ligne->cible_id }}
                                @endif
                            </td>
                            <td class="text-gris-texte">
                                @foreach ($ligne->details ?? [] as $cle => $valeur)
                                    <span class="block">{{ str_replace('_', ' ', $cle) }} : {{ is_scalar($valeur) || $valeur === null ? ($valeur ?? '–') : json_encode($valeur, JSON_UNESCAPED_UNICODE) }}</span>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-gris-texte">Aucune action enregistrée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">{{ $journal->links() }}</div>
</x-admin-shell>
