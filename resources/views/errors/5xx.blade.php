{{-- Repli pour les autres erreurs 5xx. --}}
<x-erreur :code="$exception->getStatusCode()" titre="Un souci de notre côté">
    <p>Quelque chose s'est mal passé sur le site. Ce n'est pas de ta faute.</p>
    <p class="text-gris-texte">Réessaie dans quelques minutes.</p>
</x-erreur>
