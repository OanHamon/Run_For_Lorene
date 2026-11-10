{{-- Repli pour les autres erreurs 4xx ; 401, 402, 403 et 429 y sont renvoyées explicitement (voir 403.blade.php). --}}
@php
    $code = $exception->getStatusCode();
    $titre = match ($code) {
        401 => 'Connexion nécessaire',
        403 => 'Accès refusé',
        429 => 'Trop de tentatives',
        default => 'Demande impossible',
    };
@endphp

<x-erreur :code="$code" :titre="$titre">
    @if ($code === 401)
        <p>Connecte-toi pour accéder à cette page.</p>
        <p class="text-gris-texte">Repars de l'accueil, puis connecte-toi à ton espace.</p>
    @elseif ($code === 403)
        <p>Tu n'as pas accès à cette page.</p>
        <p class="text-gris-texte">Vérifie le compte utilisé pour te connecter, ou repars de l'accueil.</p>
    @elseif ($code === 429)
        <p>Tu as fait beaucoup de tentatives d'affilée.</p>
        <p class="text-gris-texte">Patiente une minute, puis réessaie.</p>
    @else
        <p>Cette demande n'a pas pu aboutir.</p>
        <p class="text-gris-texte">Repars de l'accueil et réessaie.</p>
    @endif
</x-erreur>
