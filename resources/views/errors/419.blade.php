{{-- Fréquente dans le tunnel : formulaire resté ouvert trop longtemps, jeton CSRF expiré. --}}
<x-erreur code="419" titre="Page expirée">
    <p>Cette page est restée ouverte trop longtemps : par sécurité, ton envoi n'a pas été pris en compte.</p>
    <p class="text-gris-texte">Recharge la page, puis renvoie le formulaire.</p>

    <x-slot:actions>
        <a href="{{ url()->previous() }}" class="rfl-btn">Recharger la page</a>
    </x-slot:actions>
</x-erreur>
