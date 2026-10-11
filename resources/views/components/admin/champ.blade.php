@props(['name', 'label', 'type' => 'text', 'value' => null, 'aide' => null, 'sac' => 'default', 'id' => null])

{{-- Champ du back-office : libellé, saisie (ou slot pour un select), aide et erreur. --}}
@php
    $id ??= $sac === 'default' ? $name : "{$sac}-{$name}";
    $erreur = $errors->getBag($sac)->first($name);
    // Après une erreur, on réaffiche la saisie, mais seulement pour le formulaire concerné.
    $valeur = $errors->getBag($sac)->any() ? old($name, $value) : $value;
@endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="rfl-label">{{ $label }}</label>
    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $valeur }}"
            {{ $attributes->except('class')->merge(['class' => 'rfl-champ-compact']) }}
            @if ($aide) aria-describedby="{{ $id }}-aide" @endif
            @if ($erreur) aria-invalid="true" @endif>
    @endif
    @if ($aide)
        <p id="{{ $id }}-aide" class="mt-1 text-xs text-gris-texte">{{ $aide }}</p>
    @endif
    @if ($erreur)
        <p class="rfl-erreur">{{ $erreur }}</p>
    @endif
</div>
