{{-- Champs d'un tarif ; $sac : sac d'erreurs du formulaire ; dates bornées à la période d'inscription. --}}
@php
    $evenementTarif = $evenement ?? $tarif->course->evenement;
    $min = $evenementTarif->ouverture->format('Y-m-d');
    $max = $evenementTarif->fermeture->format('Y-m-d');
@endphp
<div class="grid gap-4 sm:grid-cols-2">
    <x-admin.champ name="libelle" label="Libellé" :value="$tarif->libelle" :sac="$sac" required maxlength="100" aide="Par exemple « Plein tarif », « Tarif early » ou « Enfant »." />
    <x-admin.champ name="montant" label="Montant (€)" type="number" step="0.01" min="0" :value="$tarif->montant" :sac="$sac" required />
    <x-admin.champ name="valide_du" label="Valable du" type="date" :value="$tarif->valide_du?->format('Y-m-d')" :min="$min" :max="$max" :sac="$sac" required />
    <x-admin.champ name="valide_au" label="au (inclus)" type="date" :value="$tarif->valide_au?->format('Y-m-d')" :min="$min" :max="$max" :sac="$sac" required />
    <x-admin.champ name="age_min" label="Âge minimum (facultatif)" type="number" min="0" max="120" :value="$tarif->age_min" :sac="$sac" />
    <x-admin.champ name="age_max" label="Âge maximum (facultatif)" type="number" min="0" max="120" :value="$tarif->age_max" :sac="$sac" />
</div>
<p class="mt-3 text-xs text-gris-texte">
    Inscriptions ouvertes du {{ $evenementTarif->ouverture->format('d/m/Y') }} au {{ $evenementTarif->fermeture->format('d/m/Y') }} : le tarif doit rester dans cette période.
    Âge calculé le jour de l'événement. Pour les mêmes jours, deux tarifs ne peuvent pas viser les mêmes âges (par exemple « Enfant » jusqu'à 17 ans et « Adulte » 18 ans et plus).
</p>
