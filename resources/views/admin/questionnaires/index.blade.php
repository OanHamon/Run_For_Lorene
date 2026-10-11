<x-admin-shell title="Questionnaires de santé">
    <x-admin.titre titre="Questionnaires de santé" sous-titre="Participants mineurs le jour de l'événement. Un « oui » ne bloque pas l'inscription : consulte, contacte la famille si besoin, puis marque comme vérifié.">
        @if ($evenement)<x-admin.filtre-evenement :evenement="$evenement" :evenements="$evenements" />@endif
    </x-admin.titre>

    <nav class="mb-6 flex gap-2 overflow-x-auto text-sm" aria-label="Filtrer les questionnaires">
        @foreach (['a_verifier' => 'À vérifier', 'oui' => 'À vérifier, avec au moins un « oui »', 'verifies' => 'Vérifiés'] as $cle => $libelle)
            <a href="{{ route('admin.questionnaires.index', ['filtre' => $cle]) }}" @if ($filtre === $cle) aria-current="page" @endif
                class="shrink-0 rounded-full px-4 py-2 {{ $filtre === $cle ? 'bg-vert font-bold text-white' : 'bg-white ring-1 ring-bordure hover:bg-fond' }}">{{ $libelle }}</a>
        @endforeach
    </nav>

    <div class="rfl-carte overflow-hidden">
        <div class="overflow-x-auto">
            <table class="rfl-tableau min-w-[640px]">
                <thead>
                    <tr><th>Participant</th><th>Course</th><th>Rempli le</th><th class="text-right">Réponses « oui »</th><th>Vérification</th></tr>
                </thead>
                <tbody>
                    @forelse ($questionnaires as $q)
                        @php($i = $q->document->inscription)
                        <tr class="relative hover:bg-fond/60">
                            <td class="font-bold"><a href="{{ route('admin.questionnaires.show', $q->id_document) }}" class="after:absolute after:inset-0">{{ $i->participant->user->name }}</a></td>
                            <td>{{ $i->tarif->course->nom }}</td>
                            <td class="text-gris-texte">{{ $q->document->date_depot->format('d/m/Y') }}</td>
                            <td class="text-right">
                                @if ($q->nb_oui > 0)
                                    <span class="rfl-badge bg-moutarde/20 text-vert-sombre">{{ $q->nb_oui }}</span>
                                @else
                                    <span class="text-gris-texte">0</span>
                                @endif
                            </td>
                            <td class="text-gris-texte">
                                @if ($q->document->date_validation)
                                    {{ $q->document->date_validation->format('d/m/Y') }} par {{ $q->document->validateur?->name }}
                                @else
                                    à vérifier
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-gris-texte">Aucun questionnaire dans cette liste.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-admin-shell>
