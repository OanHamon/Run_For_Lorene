{{--
    TRACK : empreinte de la course (paire de semelles en particules vivantes, un cœur au milieu).
    `graine` rend chaque empreinte unique (placement et nuances des particules).
    `code` : le jeton du billet (« RFL-{n°}-{signature} »). Les cellules des semelles le portent
    (vert sombre / vert doux) : le poste de retrait le lit comme un QR. Seul notre lecteur le
    comprend, pas l'appareil photo d'un téléphone.
    `statique` coupe animation et interactions (impression).
    La largeur se règle par la classe : <x-track.empreinte :code="$jeton" class="w-72" />
--}}
@props(['graine' => null, 'code' => null, 'statique' => false, 'libelle' => null])

@once
    @vite('resources/js/track.js')
@endonce

@php
    $graine ??= $code ?? 'track';
    $libelle ??= $code ? 'Empreinte Run For Lorène : code du billet' : 'Empreinte Run For Lorène';
@endphp

<div {{ $attributes->merge(['class' => 'relative aspect-[8/9] touch-manipulation select-none']) }}
     data-track data-graine="{{ $graine }}" @if ($statique) data-statique @endif
     @if ($code) data-code="{{ $code }}" @endif>
    <canvas class="absolute inset-0 h-full w-full" role="img" aria-label="{{ $libelle }}"></canvas>
</div>
