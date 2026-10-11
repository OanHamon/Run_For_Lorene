{{-- Champs de l'événement (création et onglet Général). --}}
<div class="grid gap-5 sm:grid-cols-2">
    <x-admin.champ name="nom" label="Nom" :value="$evenement->nom" required maxlength="150" class="sm:col-span-2" />
    <x-admin.champ name="date_evenement" label="Date de l'événement" type="date" :value="$evenement->date_evenement?->format('Y-m-d')" required />
    <x-admin.champ name="statut" label="Statut" aide="Seul un événement publié apparaît sur le site.">
        <select id="statut" name="statut" class="rfl-champ-compact">
            @foreach (\App\Http\Controllers\Admin\EvenementController::STATUTS as $valeur => $libelle)
                <option value="{{ $valeur }}" @selected(old('statut', $evenement->statut) === $valeur)>{{ $libelle }}</option>
            @endforeach
        </select>
    </x-admin.champ>
    <x-admin.champ name="ouverture" label="Ouverture des inscriptions" type="datetime-local" :value="$evenement->ouverture?->format('Y-m-d\TH:i')" required />
    <x-admin.champ name="fermeture" label="Fermeture des inscriptions" type="datetime-local" :value="$evenement->fermeture?->format('Y-m-d\TH:i')" required />
</div>
