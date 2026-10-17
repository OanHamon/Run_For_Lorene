<x-admin-shell title="Créer un compte">
    <p class="mb-2 text-sm">
        @if ($retour === 'inscription')
            <a href="{{ route('admin.inscriptions.create', ['email' => $email]) }}" class="rfl-lien">Inscription gratuite</a>
        @elseif (auth()->user()->peutAdmin(3))
            <a href="{{ route('admin.utilisateurs.index') }}" class="rfl-lien">Utilisateurs</a>
        @endif
    </p>
    <x-admin.titre titre="Créer un compte" sous-titre="Un compte comme ceux du site : la personne reçoit un lien pour choisir son mot de passe, puis accède à son espace." />

    <form method="POST" action="{{ route('admin.utilisateurs.store') }}" class="rfl-carte max-w-2xl space-y-5 p-6">
        @csrf
        @if ($retour)<input type="hidden" name="retour" value="{{ $retour }}">@endif

        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.champ name="prenom" label="Prénom" required maxlength="100" />
            <x-admin.champ name="nom" label="Nom" required maxlength="100" />
        </div>
        <x-admin.champ name="email" label="Email" type="email" :value="$email" required maxlength="254" autocomplete="off" aide="Relis-la avec la personne : c'est là que partira le lien, et ce sera son identifiant de connexion." />
        <x-admin.champ name="telephone" label="Téléphone (facultatif)" type="tel" placeholder="06 12 34 56 78" />

        <p class="rfl-alerte-info text-sm">
            En validant, {{ $email ?: 'la personne' }} reçoit l'email « Ton compte Run For Lorène est prêt » avec un bouton « Créer mon mot de passe », valable {{ \App\Support\Activation::VALIDITE_JOURS }} jours.
            @if ($retour === 'inscription') Tu reviens ensuite à l'inscription gratuite. @endif
        </p>

        <div class="flex justify-end">
            <button type="submit" class="rfl-btn px-6 py-3">Créer le compte</button>
        </div>
    </form>
</x-admin-shell>
