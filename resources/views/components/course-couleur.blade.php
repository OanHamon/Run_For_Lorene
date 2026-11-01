@props(['course', 'taille' => 'h-3 w-3'])

{{-- Pastille de la couleur de la course (choix libre de l'admin, donnée et non style : d'où le style en ligne). --}}
@if ($course->couleur)
    <span {{ $attributes->merge(['class' => "inline-block shrink-0 rounded-full ring-1 ring-bordure {$taille}"]) }} style="background-color: {{ $course->couleur }}" aria-hidden="true" data-couleur="{{ $course->couleur }}"></span>
@endif
