{{--
    TRACK : empreinte de la course (paire de semelles en particules vivantes, un cœur au milieu).
    `graine` rend chaque empreinte unique (placement et nuances des particules).
    `code` : texte à encoder (le jeton du billet). L'empreinte devient alors un vrai QR code, lisible
    par n'importe quel appareil photo et par le poste de retrait, où les semelles apparaissent.
    `statique` coupe animation et interactions (impression).
    La largeur se règle par la classe : <x-track.empreinte :code="$jeton" class="w-72" />
--}}
@props(['graine' => null, 'code' => null, 'statique' => false, 'libelle' => null])

@once
    @vite('resources/js/track.js')
@endonce

@php
    $graine ??= $code ?? 'track';
    $libelle ??= $code ? 'Empreinte Run For Lorène : QR code du billet' : 'Empreinte Run For Lorène';
@endphp

<div {{ $attributes->merge(['class' => 'relative touch-manipulation select-none '.($code ? 'aspect-square' : 'aspect-[8/9]')]) }}
     data-track data-graine="{{ $graine }}" @if ($statique) data-statique @endif
     @if ($code) data-matrice="{{ json_encode(\App\Support\Billet::matrice($code)) }}" @endif>
    <canvas class="absolute inset-0 h-full w-full" role="img" aria-label="{{ $libelle }}"></canvas>
</div>
