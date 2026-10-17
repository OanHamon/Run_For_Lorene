<x-tunnel-shell :title="'Lien plus valable · Run For Lorène'">
    @if ($dejaActive)
        <h1 class="font-display text-2xl text-vert">Ton compte est déjà prêt</h1>
        <p class="mt-2 text-sm text-gris-texte">Ce lien a déjà servi : ton mot de passe existe. Connecte-toi avec ton email et ton mot de passe.</p>
    @else
        <h1 class="font-display text-2xl text-vert">Ce lien n'est plus valable</h1>
        <p class="mt-2 text-sm text-gris-texte">Il a expiré (il est valable {{ \App\Support\Activation::VALIDITE_JOURS }} jours) ou il a été remplacé par un lien plus récent.</p>
        <p class="mt-2 text-sm text-gris-texte">Pas de souci : sur la page de connexion, tape ton email, tu recevras un code pour entrer dans ton espace. Tu pourras y choisir ton mot de passe, dans « Mon compte ».</p>
    @endif
    <a href="{{ route('login') }}" class="mt-6 rfl-btn w-full">Aller à la connexion</a>
</x-tunnel-shell>
