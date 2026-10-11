@props(['titre', 'sousTitre' => null])

{{-- En-tête de page du back-office : titre, sous-titre, actions à droite. --}}
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div class="min-w-0">
        <h1 class="font-display text-3xl text-vert">{{ $titre }}</h1>
        @if ($sousTitre)
            <p class="mt-1 text-sm text-gris-texte">{{ $sousTitre }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-3">{{ $slot }}</div>
    @endif
</div>
