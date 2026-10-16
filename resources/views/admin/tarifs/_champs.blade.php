{{-- Champs d'un tarif ; $sac : sac d'erreurs du formulaire ; dates bornées à la période d'inscription. --}}
@php
    $evenementTarif = $evenement ?? $tarif->course->evenement;
    $min = $evenementTarif->ouverture->format('Y-m-d');
    $max = $evenementTarif->fermeture->format('Y-m-d');
    // Tarif déjà choisi par des inscrits : montant, début et âges figés ; la fin peut bouger (pas avant aujourd'hui).
    $verrouille = $tarif->estVerrouille();
@endphp
@if ($verrouille)
    <p class="mb-4 rfl-alerte text-sm">
        Des inscrits ont déjà choisi ce tarif : son montant, sa date de début et sa tranche d'âge sont figés.
        Pour changer de prix, termine-le (au plus tôt aujourd'hui) puis ajoute un nouveau tarif qui commence le lendemain.
    </p>
@endif
<div class="grid gap-4 sm:grid-cols-2">
    <x-admin.champ name="libelle" label="Libellé" :value="$tarif->libelle" :sac="$sac" required maxlength="100" aide="Par exemple « Plein tarif », « Tarif early » ou « Enfant »." />
    <x-admin.champ name="montant" label="Montant (€)" type="number" step="0.01" min="0" :value="$tarif->montant" :sac="$sac" :readonly="$verrouille" required />
    <x-admin.champ name="valide_du" label="Valable du" type="date" :value="$tarif->valide_du?->format('Y-m-d')" :min="$min" :max="$max" :sac="$sac" :readonly="$verrouille" required />
    <x-admin.champ name="valide_au" label="au (inclus)" type="date" :value="$tarif->valide_au?->format('Y-m-d')" :min="$verrouille ? max($min, today()->format('Y-m-d')) : $min" :max="$max" :sac="$sac" required />
    <x-admin.champ name="age_min" label="Âge minimum (facultatif)" type="number" min="0" max="120" :value="$tarif->age_min" :sac="$sac" :readonly="$verrouille" />
    <x-admin.champ name="age_max" label="Âge maximum (facultatif)" type="number" min="0" max="120" :value="$tarif->age_max" :sac="$sac" :readonly="$verrouille" />
</div>
<p class="mt-3 text-xs text-gris-texte">
    Inscriptions ouvertes du {{ $evenementTarif->ouverture->format('d/m/Y') }} au {{ $evenementTarif->fermeture->format('d/m/Y') }} : le tarif doit rester dans cette période.
    Âge calculé le jour de l'événement. Pour les mêmes jours, deux tarifs ne peuvent pas viser les mêmes âges (par exemple « Enfant » jusqu'à 17 ans et « Adulte » 18 ans et plus).
</p>
