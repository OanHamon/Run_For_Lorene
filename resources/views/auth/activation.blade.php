<x-tunnel-shell :title="'Crée ton mot de passe · Run For Lorène'">
    <h1 class="font-display text-2xl text-vert">Bienvenue {{ $user->prenom }} !</h1>
    <p class="mt-2 text-sm text-gris-texte">Choisis ton mot de passe pour accéder à ton espace. Tu te connecteras ensuite avec ton email <strong class="text-vert-sombre">{{ $user->email }}</strong> et ce mot de passe.</p>

    {{-- L'URL signée (avec sa clé) est reprise telle quelle pour l'envoi. --}}
    <form method="POST" action="{{ request()->fullUrl() }}" class="mt-6 space-y-5">
        @csrf
        <div>
            <label for="mot_de_passe" class="rfl-label">Mot de passe</label>
            <input id="mot_de_passe" name="mot_de_passe" type="password" autocomplete="new-password" required autofocus class="rfl-champ" aria-describedby="mdp-aide">
            <p id="mdp-aide" class="mt-1 text-xs text-gris-texte">8 caractères au moins.</p>
            @error('mot_de_passe')<p class="rfl-erreur">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="mot_de_passe_confirmation" class="rfl-label">Confirme-le</label>
            <input id="mot_de_passe_confirmation" name="mot_de_passe_confirmation" type="password" autocomplete="new-password" required class="rfl-champ">
        </div>
        <button type="submit" class="rfl-btn w-full">Créer mon mot de passe</button>
    </form>
</x-tunnel-shell>
