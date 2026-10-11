@php
    use App\Models\Tarif;
    use App\Support\Format;

    $peutModifier = auth()->user()->peutAdmin(3);
    $nouvelle = ! $course->exists;
@endphp
<x-admin-shell :title="$nouvelle ? 'Nouvelle course' : $course->nom">
    <p class="mb-2 text-sm">
        <a href="{{ route('admin.evenements.show', [$evenement, 'onglet' => 'courses']) }}" class="rfl-lien">{{ $evenement->nom }}</a>
    </p>
    <x-admin.titre :titre="$nouvelle ? 'Nouvelle course' : $course->nom" :sous-titre="$nouvelle ? null : ($inscriptions.' inscription(s) sur cette course')">
        @if (! $nouvelle && $peutModifier && $inscriptions === 0)
            <form method="POST" action="{{ route('admin.courses.destroy', $course) }}" onsubmit="return confirm('Supprimer cette course et ses tarifs ?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="rfl-btn-secondaire px-4 py-2 text-sm">Supprimer la course</button>
            </form>
        @endif
    </x-admin.titre>

    <div class="grid gap-6 xl:grid-cols-5">
        <form method="POST" action="{{ $nouvelle ? route('admin.courses.store', $evenement) : route('admin.courses.update', $course) }}" class="rfl-carte p-6 xl:col-span-3">
            @csrf
            @unless ($nouvelle) @method('PUT') @endunless
            <fieldset @disabled(! $peutModifier)>
                <legend class="mb-4 font-display text-vert">Course</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-admin.champ name="nom" label="Nom" :value="$course->nom" required maxlength="100" class="sm:col-span-2" />
                    <x-admin.champ name="heure_depart" label="Heure de départ" type="time" :value="$course->heure_depart ? substr($course->heure_depart, 0, 5) : null" required />
                    <x-admin.champ name="distance" label="Distance (km, facultatif)" type="number" step="0.01" min="0" :value="$course->distance" />
                    <x-admin.champ name="age_min" label="Âge minimum" type="number" min="0" max="120" :value="$course->age_min" aide="Vide : pas de minimum. Âge le jour de l'événement." />
                    <x-admin.champ name="age_max" label="Âge maximum" type="number" min="0" max="120" :value="$course->age_max" aide="Vide : pas de maximum." />
                    <x-admin.champ name="quota" label="Quota" type="number" min="1" :value="$course->quota" aide="Vide : places illimitées." class="sm:col-span-2" />
                    <x-admin.champ name="dossard_debut" label="Premier dossard" type="number" min="1" :value="$course->dossard_debut" />
                    <x-admin.champ name="dossard_fin" label="Dernier dossard" type="number" min="1" :value="$course->dossard_fin" aide="Attribués dans l'ordre à la validation." />
                </div>
            </fieldset>
            @if ($peutModifier)
                <div class="mt-6 flex justify-end">
                    <button type="submit" class="rfl-btn px-6 py-3">{{ $nouvelle ? 'Créer la course' : 'Enregistrer' }}</button>
                </div>
            @endif
        </form>

        @unless ($nouvelle)
            <div class="space-y-6 xl:col-span-2">
                <div class="rfl-carte overflow-hidden">
                    <h2 class="px-6 py-4 font-display text-vert">Tarifs</h2>
                    <table class="rfl-tableau">
                        <thead><tr><th>Tarif</th><th>Période</th><th class="text-right">Montant</th></tr></thead>
                        <tbody>
                            @forelse ($course->tarifs as $tarif)
                                <tr class="relative hover:bg-fond/60">
                                    <td class="font-bold">
                                        <a href="{{ route('admin.tarifs.edit', $tarif) }}" class="after:absolute after:inset-0">{{ $tarif->libelle }}</a>
                                        @if ($tarif->age_min !== null || $tarif->age_max !== null)
                                            <span class="block text-xs font-normal text-gris-texte">{{ $tarif->age_min ?? 0 }}–{{ $tarif->age_max ?? '∞' }} ans</span>
                                        @endif
                                    </td>
                                    <td class="text-gris-texte">{{ $tarif->valide_du->format('d/m/Y') }} → {{ $tarif->valide_au->format('d/m/Y') }}</td>
                                    <td class="text-right font-bold">{{ Format::euros($tarif->montant) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-gris-texte">Aucun tarif : la course n'est pas proposée.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($peutModifier)
                    <form method="POST" action="{{ route('admin.tarifs.store', $course) }}" class="rfl-carte p-6">
                        @csrf
                        <h2 class="mb-4 font-display text-vert">Ajouter un tarif</h2>
                        @include('admin.tarifs._champs', ['tarif' => new Tarif(['libelle' => 'Plein tarif', 'valide_du' => today(), 'valide_au' => $evenement->date_evenement]), 'sac' => 'tarif'])
                        <button type="submit" class="mt-5 rfl-btn w-full px-5 py-2.5 text-sm">Ajouter le tarif</button>
                    </form>
                @endif
            </div>
        @endunless
    </div>
</x-admin-shell>
