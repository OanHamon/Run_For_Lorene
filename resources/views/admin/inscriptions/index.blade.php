@php
    use App\Models\Inscription;
    use App\Support\Format;

    $filtres = request()->only(['q', 'course', 'statut', 'mineur', 'documents']);
    $peutGerer = auth()->user()->peutAdmin(2);
@endphp
<x-admin-shell title="Inscriptions">
    <x-admin.titre titre="Inscriptions" :sous-titre="$evenement?->nom">
        @if ($evenement)
            <x-admin.filtre-evenement :evenement="$evenement" :evenements="$evenements" />
            @if ($peutGerer && Route::has('admin.inscriptions.create'))
                <a href="{{ route('admin.inscriptions.create') }}" class="rfl-btn px-5 py-2.5 text-sm">Inscription gratuite</a>
            @endif
        @endif
    </x-admin.titre>

    @if (! $evenement)
        <div class="rfl-carte px-6 py-10 text-center text-gris-texte">Aucun événement.</div>
    @else
        <form method="GET" class="rfl-carte mb-6 grid gap-4 p-5 sm:grid-cols-2 xl:grid-cols-[2fr_1fr_1fr_auto_auto]" role="search">
            <div>
                <label for="q" class="rfl-label">Rechercher</label>
                <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="Nom, email, dossard ou n°" class="rfl-champ-compact">
            </div>
            <div>
                <label for="course" class="rfl-label">Course</label>
                <select id="course" name="course" class="rfl-champ-compact">
                    <option value="">Toutes</option>
                    @foreach ($courses as $c)
                        <option value="{{ $c->id_course }}" @selected((int) request('course') === $c->id_course)>{{ $c->nom }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="statut" class="rfl-label">Statut</label>
                <select id="statut" name="statut" class="rfl-champ-compact">
                    <option value="">Tous (hors brouillons)</option>
                    @foreach (Inscription::LIBELLES as $valeur => $libelle)
                        <option value="{{ $valeur }}" @selected(request('statut') === $valeur)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
            <fieldset class="flex flex-col justify-end gap-1 pb-1 text-sm">
                <legend class="sr-only">Autres filtres</legend>
                <label class="flex items-center gap-2"><input type="checkbox" name="mineur" value="1" @checked(request()->boolean('mineur'))> Mineurs</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="documents" value="1" @checked(request()->boolean('documents'))> Documents manquants</label>
            </fieldset>
            <div class="flex items-end gap-2">
                <button type="submit" class="rfl-btn px-5 py-2.5 text-sm">Filtrer</button>
                @if (array_filter($filtres))
                    <a href="{{ route('admin.inscriptions.index') }}" class="rfl-lien text-sm">Effacer</a>
                @endif
            </div>
        </form>

        <div class="rfl-carte overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                <p class="text-sm text-gris-texte"><span class="font-bold text-vert-sombre">{{ $inscriptions->total() }}</span> inscription(s)</p>
                <div class="flex flex-wrap gap-4 text-sm">
                    <span>Exporter la liste : <a href="{{ route('admin.inscriptions.export', $filtres) }}" class="rfl-lien">Excel</a> · <a href="{{ route('admin.inscriptions.export', [...$filtres, 'format' => 'csv']) }}" class="rfl-lien">CSV</a></span>
                    <span>Liste des dossards : <a href="{{ route('admin.inscriptions.dossards') }}" class="rfl-lien">Excel</a> · <a href="{{ route('admin.inscriptions.dossards', ['format' => 'csv']) }}" class="rfl-lien">CSV</a></span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="rfl-tableau min-w-[880px]">
                    <thead>
                        <tr><th>Participant</th><th>Course</th><th>Statut</th><th class="text-right">Dossard</th><th>Paiement</th><th>Documents</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($inscriptions as $i)
                            @php($manquants = in_array($i->statut, [Inscription::ANNULEE, Inscription::BROUILLON], true) ? [] : $i->documentsManquants())
                            <tr class="relative hover:bg-fond/60">
                                <td>
                                    <a href="{{ route('admin.inscriptions.show', $i) }}" class="font-bold after:absolute after:inset-0">{{ $i->participant->user->prenom }} {{ $i->participant->user->nom }}</a>
                                    <span class="block text-xs text-gris-texte">
                                        {{ $i->participant->ageAu($evenement->date_evenement) }} ans
                                        @if ($i->participant->responsable) · géré par {{ $i->participant->responsable->name }} @endif
                                    </span>
                                </td>
                                <td>{{ $i->tarif->course->nom }}</td>
                                <td><x-admin.statut :inscription="$i" /></td>
                                <td class="text-right font-bold">{{ $i->dossard ?? '–' }}</td>
                                <td>
                                    @if ($i->commande?->statut === \App\Models\Commande::GRATUITE)
                                        <span class="text-gris-texte">Gratuite</span>
                                    @elseif ($i->commande)
                                        {{ Format::euros($i->prix_paye) }}
                                        <span class="block text-xs text-gris-texte">commande n° {{ $i->id_commande }}</span>
                                    @else
                                        <span class="text-gris-texte">–</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($manquants)
                                        <span class="rfl-badge bg-moutarde/20 text-vert-sombre">{{ count($manquants) > 1 ? 'Décharge et santé' : (str_contains($manquants[0], 'décharge') ? 'Décharge' : 'Santé') }} à remplir</span>
                                    @elseif (! in_array($i->statut, [Inscription::ANNULEE, Inscription::BROUILLON], true))
                                        <span class="rfl-badge bg-vert-clair text-vert">Complets</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-gris-texte">{{ ($i->commande?->date_commande ?? $i->created_at)->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-gris-texte">Aucune inscription ne correspond.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6">{{ $inscriptions->links() }}</div>
    @endif
</x-admin-shell>
