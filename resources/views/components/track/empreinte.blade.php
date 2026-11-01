{{--
    TRACK : empreinte de la course (paire de semelles en particules vivantes).
    `graine` rend chaque empreinte unique (le jeton du participant, par exemple) ; elle n'encode
    encore aucune donnée. `statique` coupe animation et interactions (impression, scan).
    La largeur se règle par la classe : <x-track.empreinte graine="..." class="w-64" />
--}}
@props(['graine' => 'track', 'statique' => false, 'libelle' => 'Empreinte Run For Lorène'])

@once
    @vite('resources/js/track.js')
@endonce

<div {{ $attributes->merge(['class' => 'relative aspect-[8/9] touch-manipulation select-none']) }}
     data-track data-graine="{{ $graine }}" @if ($statique) data-statique @endif>
    <canvas class="absolute inset-0 h-full w-full" role="img" aria-label="{{ $libelle }}"></canvas>
</div>
