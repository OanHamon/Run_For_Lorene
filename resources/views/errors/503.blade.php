{{-- Maintenance (php artisan down) : pas de lien vers l'accueil, il afficherait la même page. --}}
<x-erreur code="503" titre="Petite pause technique" :accueil="false">
    <p>On améliore le site en ce moment. Il revient très vite.</p>
    <p class="text-gris-texte">Reviens dans quelques minutes.</p>
</x-erreur>
