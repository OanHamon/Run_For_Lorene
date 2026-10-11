@props(['evenement', 'evenements'])

{{-- Choix de l'événement suivi (retenu en session pour les autres rubriques). --}}
@if ($evenements->count() > 1)
    <form method="GET" class="flex items-center gap-2">
        @foreach (request()->except(['evenement', 'page']) as $cle => $valeur)
            @if (is_string($valeur))<input type="hidden" name="{{ $cle }}" value="{{ $valeur }}">@endif
        @endforeach
        <label for="filtre-evenement" class="text-sm text-gris-texte">Événement</label>
        <select id="filtre-evenement" name="evenement" class="rfl-champ-compact w-auto" onchange="this.form.submit()">
            @foreach ($evenements as $e)
                <option value="{{ $e->id_evenement }}" @selected($e->id_evenement === $evenement?->id_evenement)>{{ $e->nom }}</option>
            @endforeach
        </select>
        <noscript><button type="submit" class="rfl-btn-secondaire px-4 py-2 text-sm">Afficher</button></noscript>
    </form>
@endif
