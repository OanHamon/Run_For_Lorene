<x-tunnel-shell :title="'Retrait des dossards · '.$poste->libelle">
    <form method="POST" action="{{ route('retrait.benevole', $jeton) }}" novalidate>
        @csrf
        <h1 class="font-display text-2xl text-vert">Merci de ton aide !</h1>
        <p class="mt-1 mb-6 text-sm text-gris-texte">Poste « {{ $poste->libelle }} » · {{ $poste->evenement->nom }}. Indique ton prénom : il sera noté à chaque dossard que tu remets.</p>

        <label for="prenom" class="rfl-label">Ton prénom</label>
        <input id="prenom" name="prenom" type="text" autocomplete="given-name" autofocus required maxlength="60" value="{{ old('prenom') }}" class="rfl-champ">
        @error('prenom') <p class="rfl-erreur">{{ $message }}</p> @enderror

        <div class="mt-8 flex justify-end">
            <button type="submit" class="rfl-btn">Ouvrir le poste</button>
        </div>
    </form>
</x-tunnel-shell>
