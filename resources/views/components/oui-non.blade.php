@props(['name', 'question'])

{{-- Question fermée d'un formulaire Livewire : oui ou non, liée en direct à la propriété $name (pour afficher la suite). --}}
<div role="radiogroup" aria-labelledby="{{ $name }}-libelle" class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
    <p id="{{ $name }}-libelle" class="font-bold text-vert-sombre">{{ $question }}</p>
    <div class="flex shrink-0 gap-2">
        @foreach (['oui' => 'Oui', 'non' => 'Non'] as $valeur => $texte)
            <label class="cursor-pointer">
                <input type="radio" wire:model.live="{{ $name }}" name="{{ $name }}" value="{{ $valeur }}" class="peer sr-only">
                <span class="inline-block min-w-14 rounded-full border-2 border-bordure px-4 py-1.5 text-center text-sm font-bold text-vert-sombre transition-colors hover:border-vert/50 peer-checked:border-vert peer-checked:bg-vert-clair peer-checked:text-vert peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-vert">{{ $texte }}</span>
            </label>
        @endforeach
    </div>
</div>
@error($name) <p class="rfl-erreur">{{ $message }}</p> @enderror
