{{-- Champs d'un tarif ; $sac : sac d'erreurs du formulaire. --}}
<div class="grid gap-4 sm:grid-cols-2">
    <x-admin.champ name="libelle" label="Libellé" :value="$tarif->libelle" :sac="$sac" required maxlength="100" aide="Par exemple « Plein tarif » ou « Tarif early »." />
    <x-admin.champ name="montant" label="Montant (€)" type="number" step="0.01" min="0" :value="$tarif->montant" :sac="$sac" required />
    <x-admin.champ name="valide_du" label="Valable du" type="date" :value="$tarif->valide_du?->format('Y-m-d')" :sac="$sac" required />
    <x-admin.champ name="valide_au" label="au" type="date" :value="$tarif->valide_au?->format('Y-m-d')" :sac="$sac" required />
    <x-admin.champ name="age_min" label="Âge minimum (facultatif)" type="number" min="0" max="120" :value="$tarif->age_min" :sac="$sac" />
    <x-admin.champ name="age_max" label="Âge maximum (facultatif)" type="number" min="0" max="120" :value="$tarif->age_max" :sac="$sac" />
</div>
<p class="mt-3 text-xs text-gris-texte">Âge calculé le jour de l'événement. Si plusieurs tarifs conviennent, le moins cher s'applique.</p>
