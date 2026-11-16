@php
    use App\Models\CandidatureBenevole;
    use App\Support\Benevolat;

    $filtres = request()->only(['q', 'statut', 'mission', 'mineur', 'sans_photo']);
    $peutGerer = auth()->user()->peutAdmin(2);
    $superAdmin = auth()->user()->peutAdmin(3);
    $badges = [
        CandidatureBenevole::RECUE => 'bg-moutarde/20 text-vert-sombre',
        CandidatureBenevole::INFOS_DEMANDEES => 'bg-moutarde/20 text-vert-sombre',
        CandidatureBenevole::EN_ETUDE => 'bg-fond text-vert-sombre',
        CandidatureBenevole::CONFIRMEE => 'bg-vert-clair text-vert',
    ];
@endphp
<x-admin-shell title="Bénévoles">
    <x-admin.titre titre="Bénévoles" :sous-titre="$evenement?->nom">
        @if ($superAdmin)
            <form method="POST" action="{{ route('admin.benevoles.ouverture') }}" class="flex items-center gap-3">
                @csrf @method('PUT')
                <input type="hidden" name="ouvert" value="{{ $ouvert ? 0 : 1 }}">
                <span class="text-sm text-gris-texte">Candidatures {{ $ouvert ? 'ouvertes' : 'fermées' }}</span>
                <button type="submit" class="{{ $ouvert ? 'rfl-btn-secondaire' : 'rfl-btn' }} px-5 py-2.5 text-sm"
                    onclick="return confirm('{{ $ouvert ? 'Fermer les candidatures ? Plus personne ne pourra candidater.' : 'Ouvrir les candidatures sur le site ?' }}')">{{ $ouvert ? 'Fermer' : 'Ouvrir les candidatures' }}</button>
            </form>
        @else
            <span class="text-sm text-gris-texte">Candidatures {{ $ouvert ? 'ouvertes' : 'fermées' }}</span>
        @endif
    </x-admin.titre>

    @if (! $evenement)
        <div class="rfl-carte px-6 py-10 text-center text-gris-texte">Aucun événement.</div>
    @else
        {{-- Où en sont les candidatures : un chiffre par statut, cliquable. --}}
        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
            @foreach (CandidatureBenevole::LIBELLES as $valeur => $libelle)
                <a href="{{ route('admin.benevoles.index', ['statut' => $valeur]) }}" class="rfl-carte p-4 hover:bg-fond/60 {{ request('statut') === $valeur ? 'ring-2 ring-vert' : '' }}">
                    <p class="text-xs text-gris-texte">{{ $libelle }}</p>
                    <p class="mt-1 font-display text-2xl text-vert">{{ $compteurs[$valeur] ?? 0 }}</p>
                </a>
            @endforeach
        </div>

        <form method="GET" class="rfl-carte mb-6 grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-[2fr_1fr_1fr_auto_auto]" role="search">
            <div>
                <label for="q" class="rfl-label">Rechercher</label>
                <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="Nom ou email" class="rfl-champ-compact">
            </div>
            <div>
                <label for="statut" class="rfl-label">Statut</label>
                <select id="statut" name="statut" class="rfl-champ-compact">
                    <option value="">Toutes (hors non envoyées)</option>
                    @foreach (CandidatureBenevole::LIBELLES as $valeur => $libelle)
                        <option value="{{ $valeur }}" @selected(request('statut') === $valeur)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="mission" class="rfl-label">Mission</label>
                <select id="mission" name="mission" class="rfl-champ-compact">
                    <option value="">Toutes</option>
                    @foreach (Benevolat::MISSIONS as $code => $m)
                        <option value="{{ $code }}" @selected(request('mission') === $code)>{{ $m['libelle'] }}</option>
                    @endforeach
                </select>
            </div>
            <fieldset class="flex flex-col justify-end gap-1 pb-1 text-sm">
                <legend class="sr-only">Autres filtres</legend>
                <label class="flex items-center gap-2"><input type="checkbox" name="mineur" value="1" @checked(request()->boolean('mineur'))> Mineurs</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="sans_photo" value="1" @checked(request()->boolean('sans_photo'))> Sans photo</label>
            </fieldset>
            <div class="flex items-end gap-2">
                <button type="submit" class="rfl-btn px-5 py-2.5 text-sm">Filtrer</button>
                @if (array_filter($filtres))
                    <a href="{{ route('admin.benevoles.index') }}" class="rfl-lien text-sm">Effacer</a>
                @endif
            </div>
        </form>

        <div class="rfl-carte overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                <p class="text-sm text-gris-texte"><span class="font-bold text-vert-sombre">{{ $candidatures->total() }}</span> candidature(s)</p>
                @if ($peutGerer)
                    <div class="flex flex-wrap gap-4 text-sm">
                        <a href="{{ route('admin.benevoles.export', $filtres) }}" class="rfl-lien">Exporter la liste (CSV)</a>
                        <a href="{{ route('admin.benevoles.photos', $filtres) }}" class="rfl-lien">Photos des badges (ZIP)</a>
                    </div>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="rfl-tableau min-w-[820px]">
                    <thead>
                        <tr><th>Bénévole</th><th>Missions préférées</th><th>Jour J</th><th>Statut</th><th>Photo</th><th>Envoyée le</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($candidatures as $c)
                            <tr class="relative hover:bg-fond/60">
                                <td>
                                    <a href="{{ route('admin.benevoles.show', $c) }}" class="font-bold after:absolute after:inset-0">{{ $c->user->prenom }} {{ $c->user->nom }}</a>
                                    <span class="block text-xs text-gris-texte">{{ Benevolat::AGES[$c->age] ?? 'âge non renseigné' }}{{ $c->court ? ' · court aussi' : '' }}</span>
                                </td>
                                <td class="text-sm">
                                    {{ collect($c->missions_preferees)->take(3)->map(fn ($m, $i) => ($i + 1).'. '.Benevolat::mission($m))->join(', ') ?: '–' }}
                                    @if ($c->mission_affectee)<span class="block text-xs font-bold text-vert">Affecté(e) : {{ Benevolat::mission($c->mission_affectee) }}</span>@endif
                                </td>
                                <td class="text-sm text-gris-texte">{{ ($c->disponibilites['toute_journee'] ?? false) ? 'Toute la journée' : count($c->disponibilites['tranches'] ?? []).' créneau(x)' }}</td>
                                <td><span class="rfl-badge {{ $badges[$c->statut] ?? 'bg-bordure text-gris-texte' }}">{{ $c->libelleStatut() }}</span></td>
                                <td class="text-sm">{{ $c->photo ? 'Oui' : '–' }}@if ($c->autorisationRequise() && ! $c->autorisation_parentale)<span class="block text-xs text-erreur">Autorisation manquante</span>@endif</td>
                                <td class="whitespace-nowrap text-gris-texte">{{ $c->envoyee_le?->format('d/m/Y') ?? '–' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-gris-texte">Aucune candidature ne correspond.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">{{ $candidatures->links() }}</div>
    @endif
</x-admin-shell>
