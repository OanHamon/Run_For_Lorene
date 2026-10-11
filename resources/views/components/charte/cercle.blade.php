{{-- Cercle de la charte (p. 11) : un disque plein entouré d'un arc décalé d'une autre couleur. --}}
@props(['disque' => 'bg-white', 'arc' => 'border-moutarde'])

<div {{ $attributes->merge(['class' => 'relative aspect-square']) }}>
    <div class="absolute -inset-3 rounded-full border-[10px] {{ $arc }} [clip-path:polygon(50%_50%,50%_-10%,110%_-10%,110%_110%,-10%_110%,-10%_75%)]" aria-hidden="true"></div>
    <div class="absolute inset-0 flex items-center justify-center overflow-hidden rounded-full {{ $disque }}">
        {{ $slot }}
    </div>
</div>
