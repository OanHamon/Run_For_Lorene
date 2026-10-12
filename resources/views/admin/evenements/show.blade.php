@php
    use App\Http\Controllers\Admin\EvenementController;
    use App\Support\Format;

    $peutModifier = auth()->user()->peutAdmin(3);
    $ages = fn ($min, $max) => match (true) {
        $min === null && $max === null => 'Tous âges',
        $max === null => "{$min} ans et plus",
        $min === null => "Jusqu'à {$max} ans",
        default => "{$min} à {$max} ans",
    };
@endphp
<x-admin-shell :title="$evenement->nom">
    <p class="mb-2 text-sm"><a href="{{ route('admin.evenements.index') }}" class="rfl-lien">Événements</a></p>
    <x-admin.titre :titre="$evenement->nom" :sous-titre="$evenement->date_evenement->translatedFormat('l j F Y').' · '.($evenement->statut === 'publie' ? 'publié' : 'brouillon')">
        @if ($peutModifier)
            <form method="POST" action="{{ route('admin.evenements.dupliquer', $evenement) }}" onsubmit="return confirm('Dupliquer cet événement pour l\'édition suivante ?')">
                @csrf
                <button type="submit" class="rfl-btn-secondaire px-5 py-2 text-sm">Dupliquer pour l'an prochain</button>
            </form>
        @endif
    </x-admin.titre>

    <nav class="mb-6 flex gap-2 overflow-x-auto text-sm" aria-label="Onglets de l'événement">
        @foreach ($onglets as $cle => $libelle)
            <a href="{{ route('admin.evenements.show', [$evenement, 'onglet' => $cle]) }}" @if ($onglet === $cle) aria-current="page" @endif
                class="shrink-0 rounded-full px-4 py-2 {{ $onglet === $cle ? 'bg-vert font-bold text-white' : 'bg-white ring-1 ring-bordure hover:bg-fond' }}">{{ $libelle }}</a>
        @endforeach
    </nav>

    @if ($onglet === 'general')
        <form method="POST" action="{{ route('admin.evenements.update', $evenement) }}" class="rfl-carte max-w-3xl p-6">
            @csrf
            @method('PUT')
            <fieldset @disabled(! $peutModifier)>
                <legend class="sr-only">Informations générales</legend>
                @include('admin.evenements._champs')
            </fieldset>
            @if ($peutModifier)
                <div class="mt-6 flex justify-end"><button type="submit" class="rfl-btn px-6 py-3">Enregistrer</button></div>
            @endif
        </form>

    @elseif ($onglet === 'courses')
        <div class="space-y-4">
            @forelse ($evenement->courses as $course)
                <div class="rfl-carte p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-display text-lg text-vert">{{ $course->nom }}</h2>
                            <p class="mt-1 text-sm text-gris-texte">
                                Départ {{ substr($course->heure_depart, 0, 5) }}
                                @if ($course->distance) · {{ rtrim(rtrim(number_format((float) $course->distance, 2, ',', ''), '0'), ',') }} km @endif
                                · {{ $ages($course->age_min, $course->age_max) }}
                                · {{ $course->quota ? "{$course->quota} places" : 'places illimitées' }}
                                · dossards {{ $course->dossard_debut !== null ? "{$course->dossard_debut}–{$course->dossard_fin}" : 'non définis' }}
                            </p>
                        </div>
                        <a href="{{ route('admin.courses.edit', $course) }}" class="rfl-btn-secondaire px-4 py-1.5 text-sm">{{ $peutModifier ? 'Modifier' : 'Voir' }}</a>
                    </div>
                    <ul class="mt-3 flex flex-wrap gap-2 text-sm">
                        @forelse ($course->tarifs as $tarif)
                            <li class="rounded-2xl bg-fond px-3 py-1.5">
                                <span class="font-bold">{{ $tarif->libelle }} : {{ Format::euros($tarif->montant) }}</span>
                                <span class="text-gris-texte">· du {{ $tarif->valide_du->format('d/m/Y') }} au {{ $tarif->valide_au->format('d/m/Y') }}@if ($tarif->age_min !== null || $tarif->age_max !== null) · {{ $ages($tarif->age_min, $tarif->age_max) }}@endif</span>
                            </li>
                        @empty
                            <li class="rfl-badge bg-moutarde/20 text-vert-sombre">Aucun tarif : la course n'est pas proposée</li>
                        @endforelse
                    </ul>
                </div>
            @empty
                <div class="rfl-carte px-6 py-8 text-center text-gris-texte">Aucune course pour l'instant.</div>
            @endforelse
        </div>
        @if ($peutModifier)
            <a href="{{ route('admin.courses.create', $evenement) }}" class="mt-6 rfl-btn px-5 py-2.5 text-sm">Ajouter une course</a>
        @endif

    @elseif ($onglet === 'options')
        <div class="space-y-4">
            @forelse ($evenement->optionEvenements as $option)
                <div class="rfl-carte flex flex-wrap items-start justify-between gap-3 p-5">
                    <div>
                        <h2 class="font-display text-lg text-vert">{{ $option->libelle }} · {{ (float) $option->prix > 0 ? Format::euros($option->prix) : 'offert' }}</h2>
                        <ul class="mt-2 flex flex-wrap gap-2 text-sm">
                            @forelse ($option->declinaisons as $d)
                                <li class="rounded-full px-3 py-1 {{ $d->stock === 0 ? 'bg-bordure text-gris-texte' : 'bg-fond' }}">{{ $d->libelle }} · {{ $d->stock === null ? 'illimité' : ($d->stock === 0 ? 'épuisé' : "stock {$d->stock}") }}</li>
                            @empty
                                <li class="text-gris-texte">Aucune taille.</li>
                            @endforelse
                        </ul>
                    </div>
                    <a href="{{ route('admin.options.edit', $option) }}" class="rfl-btn-secondaire px-4 py-1.5 text-sm">{{ $peutModifier ? 'Modifier' : 'Voir' }}</a>
                </div>
            @empty
                <div class="rfl-carte px-6 py-8 text-center text-gris-texte">Aucune option pour l'instant.</div>
            @endforelse
        </div>
        @if ($peutModifier)
            <a href="{{ route('admin.options.create', $evenement) }}" class="mt-6 rfl-btn px-5 py-2.5 text-sm">Ajouter une option</a>
        @endif

    @else
        <div class="grid gap-6 xl:grid-cols-3">
            <div class="rfl-carte overflow-hidden xl:col-span-2">
                <h2 class="px-6 py-4 font-display text-vert">Versions publiées</h2>
                <div class="overflow-x-auto">
                    <table class="rfl-tableau min-w-[520px]">
                        <thead><tr><th>Texte</th><th>Version</th><th>Publiée le</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($evenement->texteLegals as $texte)
                                @php($enVigueur = $textesEnVigueur->get($texte->type_texte)?->id_texte === $texte->id_texte)
                                <tr>
                                    <td class="font-bold">{{ $typesTextes[$texte->type_texte] ?? $texte->type_texte }}</td>
                                    <td>
                                        v{{ $texte->version }}
                                        @if ($enVigueur)<span class="ml-1 rfl-badge bg-vert-clair text-vert">en vigueur</span>@endif
                                    </td>
                                    <td class="text-gris-texte">{{ $texte->date_publication->format('d/m/Y') }}</td>
                                    <td class="text-right"><a href="{{ route('admin.textes.show', $texte) }}" target="_blank" rel="noopener" class="rfl-lien">PDF</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-gris-texte">Aucun texte publié.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($peutModifier)
                <form method="POST" action="{{ route('admin.textes.store', $evenement) }}" enctype="multipart/form-data" class="rfl-carte space-y-4 p-6">
                    @csrf
                    <h2 class="font-display text-vert">Publier une nouvelle version</h2>
                    <p class="text-sm text-gris-texte">Les consentements déjà donnés restent liés à la version acceptée.</p>
                    <x-admin.champ name="type_texte" label="Texte" sac="texte">
                        <select id="texte-type_texte" name="type_texte" class="rfl-champ-compact">
                            @foreach ($typesTextes as $valeur => $libelle)
                                <option value="{{ $valeur }}" @selected(old('type_texte') === $valeur)>{{ $libelle }}</option>
                            @endforeach
                        </select>
                    </x-admin.champ>
                    <x-admin.champ name="fichier" label="Fichier PDF (10 Mo max.)" type="file" sac="texte" accept="application/pdf" required class="[&_input]:pt-2" />
                    <button type="submit" class="rfl-btn w-full px-5 py-2.5 text-sm">Publier</button>
                </form>
            @endif
        </div>
    @endif
</x-admin-shell>
