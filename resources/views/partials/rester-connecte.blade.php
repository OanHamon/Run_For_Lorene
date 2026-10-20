{{-- Case « Rester connecté » des écrans mot de passe et code : décochée sur un ordinateur partagé. --}}
<label class="mt-6 flex items-start gap-3 text-sm text-vert-sombre">
    <input wire:model="resterConnecte" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 rounded border-bordure text-vert focus:ring-vert">
    <span>Rester connecté sur cet appareil <span class="text-gris-texte">(décoche sur un ordinateur partagé)</span></span>
</label>
