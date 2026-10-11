@props(['question', 'numero', 'modele'])

{{-- Question d'un questionnaire (santé ou décharge) : réponse oui ou non, liée à $modele.{id}. --}}
@php
    $cle = "{$modele}.{$question->id_question}";
    $id = str_replace('.', '-', $cle);
@endphp
<div wire:key="{{ $cle }}">
    <div role="radiogroup" aria-labelledby="{{ $id }}-libelle" class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
        <p id="{{ $id }}-libelle" class="text-sm text-vert-sombre"><span class="font-bold text-gris-texte">{{ $numero }}.</span> {{ $question->libelle }}</p>
        <div class="flex shrink-0 gap-2">
            @foreach (['oui' => 'Oui', 'non' => 'Non'] as $valeur => $texte)
                <label class="cursor-pointer">
                    <input type="radio" wire:model="{{ $cle }}" name="{{ $id }}" value="{{ $valeur }}" class="peer sr-only">
                    <span class="inline-block min-w-14 rounded-full border-2 border-bordure px-4 py-1.5 text-center text-sm font-bold text-vert-sombre transition-colors hover:border-vert/50 peer-checked:border-vert peer-checked:bg-vert-clair peer-checked:text-vert peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-vert">{{ $texte }}</span>
                </label>
            @endforeach
        </div>
    </div>
    @error($cle) <p class="rfl-erreur -mt-1 mb-2">{{ $message }}</p> @enderror
</div>
