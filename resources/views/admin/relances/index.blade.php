@php use App\Support\ModelesRelance; @endphp
<x-admin-shell title="Relances">
    <x-admin.titre titre="Relances" :sous-titre="$evenement ? 'Emails envoyés aux inscrits · '.$evenement->nom : null">
        @if ($evenement)
            <a href="{{ route('admin.relances.create') }}" class="rfl-btn px-5 py-2.5 text-sm">Nouvelle relance</a>
        @endif
    </x-admin.titre>

    @if (! $evenement)
        <div class="rfl-carte px-6 py-10 text-center text-gris-texte">Aucun événement pour l'instant.</div>
    @else
        <div class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach (ModelesRelance::tous() as $cle => $m)
                <a href="{{ route('admin.relances.create', ['modele' => $cle]) }}" class="rfl-carte block p-4 hover:bg-fond/60">
                    <p class="font-bold text-vert">{{ $m['libelle'] }}</p>
                    <p class="mt-1 text-xs text-gris-texte">{{ $m['sujet'] ?: 'Objet et message à écrire' }}</p>
                </a>
            @endforeach
        </div>

        <p class="mb-4 text-sm text-gris-texte">Les relances automatiques (documents à J-15) partent toutes seules et ne figurent pas ici ; elles restent visibles au journal de chaque inscription.</p>

        <div class="rfl-carte overflow-hidden">
            <div class="overflow-x-auto">
                <table class="rfl-tableau min-w-[640px]">
                    <thead><tr><th>Date</th><th>Objet</th><th>Modèle</th><th class="text-right">Destinataires</th><th>Par</th></tr></thead>
                    <tbody>
                        @forelse ($relances as $r)
                            <tr>
                                <td class="whitespace-nowrap">{{ $r->created_at->format('d/m/Y H:i') }}</td>
                                <td class="font-bold">{{ $r->sujet }}</td>
                                <td class="text-sm text-gris-texte">{{ ModelesRelance::tous()[$r->modele]['libelle'] ?? $r->modele }}</td>
                                <td class="text-right">{{ $r->nb_destinataires }}</td>
                                <td class="text-sm text-gris-texte">{{ $r->admin?->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-gris-texte">Aucune relance envoyée pour l'instant.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-6">{{ $relances->links() }}</div>
    @endif
</x-admin-shell>
