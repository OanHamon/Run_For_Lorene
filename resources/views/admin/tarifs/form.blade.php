@php($peutModifier = auth()->user()->peutAdmin(3))
<x-admin-shell :title="$tarif->libelle">
    <p class="mb-2 text-sm">
        <a href="{{ route('admin.evenements.show', [$tarif->course->evenement, 'onglet' => 'courses']) }}" class="rfl-lien">{{ $tarif->course->evenement->nom }}</a>
        · <a href="{{ route('admin.courses.edit', $tarif->course) }}" class="rfl-lien">{{ $tarif->course->nom }}</a>
    </p>
    <x-admin.titre :titre="$tarif->libelle" sous-titre="Modifier un tarif ne change pas le prix des inscriptions déjà passées.">
        @if ($peutModifier)
            <form method="POST" action="{{ route('admin.tarifs.destroy', $tarif) }}" onsubmit="return confirm('Supprimer ce tarif ?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="rfl-btn-secondaire px-4 py-2 text-sm">Supprimer</button>
            </form>
        @endif
    </x-admin.titre>

    <form method="POST" action="{{ route('admin.tarifs.update', $tarif) }}" class="rfl-carte max-w-2xl p-6">
        @csrf
        @method('PUT')
        <fieldset @disabled(! $peutModifier)>
            <legend class="sr-only">Tarif</legend>
            @include('admin.tarifs._champs', ['sac' => 'default'])
        </fieldset>
        @if ($peutModifier)
            <div class="mt-6 flex justify-end"><button type="submit" class="rfl-btn px-6 py-3">Enregistrer</button></div>
        @endif
    </form>
</x-admin-shell>
