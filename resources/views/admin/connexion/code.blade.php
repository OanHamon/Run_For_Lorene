<x-tunnel-shell :title="'Code de connexion · Admin Run For Lorène'">
    <div class="rfl-pastille mb-6">
        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-vert-clair text-sm font-bold text-vert">{{ Str::upper(Str::substr($email, 0, 1)) }}</span>
        <span class="truncate text-sm text-vert-sombre">{{ $email }}</span>
        <a href="{{ route('admin.connexion') }}" class="ml-1 shrink-0 text-sm rfl-lien">Modifier</a>
    </div>

    <form method="POST" action="{{ route('admin.connexion.valider') }}" novalidate>
        @csrf
        <h1 class="font-display text-2xl text-vert">Confirme ton accès</h1>
        <p class="mt-1 mb-6 text-sm text-gris-texte">Pour entrer dans l'administration, saisis le code à 6 chiffres qu'on vient de t'envoyer par email. Il est valable {{ \App\Services\Auth\CodeEmail::VALIDITE_MINUTES }} minutes. Pense à regarder dans les spams.</p>

        @if (session('statut'))
            <p class="mb-4 rounded-2xl bg-vert-clair px-4 py-3 text-sm text-vert" role="status">{{ session('statut') }}</p>
        @endif

        <label for="code" class="rfl-label">Code reçu par email</label>
        <input name="code" id="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="7" autofocus required placeholder="123456" class="rfl-champ text-center font-display text-2xl tracking-[0.5em]" aria-describedby="code-erreur">
        @if ($erreur = $errors->first('code') ?: $attente)
            <p id="code-erreur" class="rfl-erreur">{{ $erreur }}</p>
        @endif

        <div class="mt-8 flex items-center justify-between">
            <a href="{{ route('admin.connexion') }}" class="rfl-lien">Retour</a>
            <button type="submit" class="rfl-btn">Valider</button>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.connexion.renvoyer') }}" class="mt-4">
        @csrf
        <button type="submit" class="text-sm rfl-lien">Je n'ai rien reçu : renvoyer le code</button>
    </form>
</x-tunnel-shell>
