<x-tunnel-shell :title="'Connexion · Admin Run For Lorène'">
    <form method="POST" action="{{ route('admin.connexion.connecter') }}" novalidate x-data="{ voir: false }">
        @csrf
        <h1 class="font-display text-2xl text-vert">Administration</h1>
        <p class="mt-1 mb-6 text-sm text-gris-texte">Connecte-toi avec ton compte admin. On t'enverra ensuite un code par email pour confirmer.</p>

        @if (session('statut'))
            <p class="mb-4 rounded-2xl bg-vert-clair px-4 py-3 text-sm text-vert" role="status">{{ session('statut') }}</p>
        @endif

        <label for="email" class="rfl-label">Adresse email</label>
        <input name="email" id="email" type="email" inputmode="email" autocomplete="username" value="{{ old('email') }}" @unless (old('email')) autofocus @endunless required class="rfl-champ" aria-describedby="email-erreur">
        @error('email') <p id="email-erreur" class="rfl-erreur">{{ $message }}</p> @enderror

        <label for="mot_de_passe" class="rfl-label mt-4">Mot de passe</label>
        <div class="relative">
            <input name="mot_de_passe" id="mot_de_passe" :type="voir ? 'text' : 'password'" type="password" autocomplete="current-password" @if (old('email')) autofocus @endif required class="rfl-champ pr-24" aria-describedby="mot_de_passe-erreur">
            <button type="button" x-on:click="voir = !voir" class="absolute inset-y-0 right-4 text-sm rfl-lien" x-text="voir ? 'Masquer' : 'Afficher'">Afficher</button>
        </div>
        @error('mot_de_passe') <p id="mot_de_passe-erreur" class="rfl-erreur">{{ $message }}</p> @enderror

        <p class="mt-4 text-sm text-gris-texte">Mot de passe oublié ? Demande à un super admin de réinitialiser ton accès : tu recevras un lien pour en choisir un nouveau.</p>

        <div class="mt-8 flex items-center justify-between">
            <a href="{{ route('login') }}" class="rfl-lien">Connexion participant</a>
            <button type="submit" class="rfl-btn">Continuer</button>
        </div>
    </form>
</x-tunnel-shell>
