@php
    use App\Models\Inscription;
    use App\Support\Format;

    $filtres = request()->only(['q', 'course', 'statut', 'mineur', 'documents', 'sante', 'compte']);
    $superAdmin = auth()->user()->peutAdmin(3);
    $peutGerer = auth()->user()->peutAdmin(2);
@endphp
<x-admin-shell title="Inscriptions">
    <x-admin.titre titre="Inscriptions" :sous-titre="$evenement?->nom">
        @if ($evenement)
            @if ($peutGerer && Route::has('admin.inscriptions.create'))
                <a href="{{ route('admin.inscriptions.create') }}" class="rfl-btn px-5 py-2.5 text-sm">Inscription gratuite</a>
            @endif
        @endif
    </x-admin.titre>

    @if (! $evenement)
        <div class="rfl-carte px-6 py-10 text-center text-gris-texte">Aucun événement.</div>
    @else
        {{-- Deux lectures de la même liste : une ligne par coureur, ou une ligne par compte (famille, entreprise…). --}}
        <nav class="mb-4 flex flex-wrap items-center gap-2" aria-label="Affichage">
            @foreach (['inscriptions' => 'Par inscription', 'comptes' => 'Par compte'] as $cle => $libelle)
                <a href="{{ route('admin.inscriptions.index', [...$filtres, 'vue' => $cle === 'comptes' ? 'comptes' : null]) }}"
                    @if ($vue === $cle) aria-current="page" @endif
                    class="rounded-full px-4 py-2 text-sm font-bold ring-1 {{ $vue === $cle ? 'bg-vert text-white ring-vert' : 'bg-white text-vert ring-bordure hover:bg-fond' }}">{{ $libelle }}</a>
            @endforeach
            @if ($superAdmin && Route::has('admin.utilisateurs.index'))
                <a href="{{ route('admin.utilisateurs.index') }}" class="ml-auto rfl-lien text-sm">Tous les comptes, même sans inscription</a>
            @endif
        </nav>

        @if ($compte)
            <div class="rfl-alerte-info mb-4 flex flex-wrap items-center justify-between gap-3 text-sm" role="status" data-test="filtre-compte">
                <p>Compte de <strong>{{ $compte->name }}</strong>@if ($compte->email) · {{ $compte->email }}@endif : ses inscriptions et celles des participants qu'il gère.</p>
                <div class="flex flex-wrap gap-4">
                    @if ($superAdmin)
                        <a href="{{ route('admin.utilisateurs.show', $compte) }}" class="rfl-lien">Gérer le compte</a>
                    @endif
                    <a href="{{ route('admin.inscriptions.index', \Illuminate\Support\Arr::except($filtres, 'compte') + ['vue' => $vue === 'comptes' ? 'comptes' : null]) }}" class="rfl-lien">Voir tous les comptes</a>
                </div>
            </div>
        @endif

        <form method="GET" class="rfl-carte mb-6 grid gap-4 p-5 sm:grid-cols-2 {{ $peutGerer ? 'xl:grid-cols-[2fr_1fr_1fr_1fr_auto_auto]' : 'xl:grid-cols-[2fr_1fr_1fr_auto_auto]' }}" role="search">
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
            @if ($peutGerer)
                <div>
                    <label for="sante" class="rfl-label">Questionnaire de santé</label>
                    <select id="sante" name="sante" class="rfl-champ-compact">
                        <option value="">Tous</option>
                        @foreach (\App\Http\Controllers\Admin\InscriptionController::FILTRES_SANTE as $valeur => $libelle)
                            <option value="{{ $valeur }}" @selected(request('sante') === $valeur)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <fieldset class="flex flex-col justify-end gap-1 pb-1 text-sm">
                <legend class="sr-only">Autres filtres</legend>
                <label class="flex items-center gap-2"><input type="checkbox" name="mineur" value="1" @checked(request()->boolean('mineur'))> Mineurs</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="documents" value="1" @checked(request()->boolean('documents'))> Documents manquants</label>
            </fieldset>
            @if ($vue === 'comptes')<input type="hidden" name="vue" value="comptes">@endif
            @if ($compte)<input type="hidden" name="compte" value="{{ $compte->id_user }}">@endif
            <div class="flex items-end gap-2">
                <button type="submit" class="rfl-btn px-5 py-2.5 text-sm">Filtrer</button>
                @if (array_filter($filtres))
                    <a href="{{ route('admin.inscriptions.index') }}" class="rfl-lien text-sm">Effacer</a>
                @endif
            </div>
        </form>

        @if ($vue === 'comptes')
            <div class="rfl-carte overflow-hidden" data-test="vue-comptes">
                <p class="px-6 py-4 text-sm text-gris-texte"><span class="font-bold text-vert-sombre">{{ $comptes->total() }}</span> compte(s)</p>
                <div class="overflow-x-auto">
                    <table class="rfl-tableau min-w-[720px]">
                        <thead>
                            <tr><th>Compte</th><th>Inscriptions</th><th class="text-right">Nombre</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($comptes as $c)
                                <tr class="align-top">
                                    <td>
                                        <a href="{{ route('admin.inscriptions.index', ['compte' => $c->user->id_user]) }}" class="font-bold rfl-lien">{{ $c->user->name }}</a>
                                        <span class="block text-xs text-gris-texte">{{ $c->user->email ?? 'sans email' }}</span>
                                    </td>
                                    <td>
                                        <ul class="space-y-1">
                                            @foreach ($c->inscriptions as $i)
                                                <li class="flex flex-wrap items-center gap-2">
                                                    <a href="{{ route('admin.inscriptions.show', $i) }}" class="rfl-lien">{{ $i->participant->user->name }}</a>
                                                    <span class="flex items-center gap-1 text-xs text-gris-texte"><x-course-couleur :course="$i->tarif->course" />{{ $i->tarif->course->nom }}</span>
                                                    <x-admin.statut :inscription="$i" />
                                                </li>
                                            @endforeach
                                        </ul>
                                    </td>
                                    <td class="text-right font-bold">{{ $c->inscriptions->count() }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-gris-texte">Aucun compte ne correspond.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6">{{ $comptes->links() }}</div>
        @else
        <div class="rfl-carte overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                <p class="text-sm text-gris-texte"><span class="font-bold text-vert-sombre">{{ $inscriptions->total() }}</span> inscription(s)</p>
                <div class="flex flex-wrap gap-4 text-sm">
                    <a href="{{ route('admin.inscriptions.export', $filtres) }}" class="rfl-lien">Exporter la liste (CSV)</a>
                    <a href="{{ route('admin.inscriptions.dossards') }}" class="rfl-lien">Liste des dossards (CSV)</a>
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
                                        @if ($i->participant->responsable) · géré par <a href="{{ route('admin.inscriptions.index', ['compte' => $i->participant->responsable->id_user]) }}" class="relative z-10 rfl-lien">{{ $i->participant->responsable->name }}</a> @endif
                                    </span>
                                </td>
                                <td><span class="flex items-center gap-2"><x-course-couleur :course="$i->tarif->course" />{{ $i->tarif->course->nom }}</span></td>
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
    @endif
</x-admin-shell>
