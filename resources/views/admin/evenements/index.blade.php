<x-admin-shell title="Événements">
    <x-admin.titre titre="Événements" sous-titre="Une édition par ligne : ses courses, tarifs et textes légaux.">
        @if (auth()->user()->peutAdmin(3))
            <a href="{{ route('admin.evenements.create') }}" class="rfl-btn px-5 py-2.5 text-sm">Créer un événement</a>
        @endif
    </x-admin.titre>

    <div class="rfl-carte overflow-hidden">
        <div class="overflow-x-auto">
            <table class="rfl-tableau min-w-[640px]">
                <thead>
                    <tr><th>Événement</th><th>Date</th><th>Inscriptions ouvertes</th><th>Statut</th><th class="text-right">Courses</th><th class="text-right">Inscriptions</th></tr>
                </thead>
                <tbody>
                    @forelse ($evenements as $e)
                        <tr class="relative hover:bg-fond/60">
                            <td class="font-bold"><a href="{{ route('admin.evenements.show', $e) }}" class="after:absolute after:inset-0">{{ $e->nom }}</a></td>
                            <td>{{ $e->date_evenement->translatedFormat('j F Y') }}</td>
                            <td class="text-gris-texte">du {{ $e->ouverture->format('d/m/Y') }} au {{ $e->fermeture->format('d/m/Y') }}</td>
                            <td>
                                <span class="rfl-badge {{ $e->statut === 'publie' ? 'bg-vert-clair text-vert' : 'bg-bordure text-gris-texte' }}">{{ $e->statut === 'publie' ? 'Publié' : 'Brouillon' }}</span>
                            </td>
                            <td class="text-right">{{ $e->courses_count }}</td>
                            <td class="text-right">{{ $e->inscriptions_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-gris-texte">Aucun événement.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-shell>
