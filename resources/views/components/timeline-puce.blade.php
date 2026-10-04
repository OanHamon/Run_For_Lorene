@props(['etat'])

{{-- Puce de la timeline d'une inscription : fait, en cours, à faire ou non requis. --}}
@if ($etat === 'fait')
    <span class="relative z-10 flex h-6 w-6 items-center justify-center rounded-full bg-canard" aria-hidden="true">
        <svg class="h-3.5 w-3.5 text-white" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" /></svg>
    </span>
@elseif ($etat === 'non_requis')
    <span class="relative z-10 flex h-6 w-6 items-center justify-center rounded-full border-2 border-bordure bg-white" aria-hidden="true">
        <span class="h-0.5 w-2.5 rounded bg-gris-texte/50"></span>
    </span>
@elseif ($etat === 'en_cours')
    <span class="relative z-10 flex h-6 w-6 items-center justify-center rounded-full border-2 border-moutarde bg-white" aria-hidden="true">
        <span class="h-2.5 w-2.5 rounded-full bg-moutarde"></span>
    </span>
@else
    <span class="relative z-10 h-6 w-6 rounded-full border-2 border-moutarde bg-white" aria-hidden="true"></span>
@endif
