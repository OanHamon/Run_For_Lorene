{{--
    Réponses d'une candidature bénévole, en lecture (espace du bénévole et fiche admin).
    $sensible : contacts d'urgence, responsable légal et allergies (admins habilités et bénévole lui-même).
--}}
@php
    use App\Support\Benevolat;
    use App\Support\Telephone;

    $dispos = $c->disponibilites ?? [];
    $ouiNon = fn (?bool $v) => $v === null ? '–' : ($v ? 'Oui' : 'Non');
@endphp
<dl class="mt-4 grid gap-x-8 gap-y-4 text-sm sm:grid-cols-2">
    <div><dt class="text-gris-texte">Téléphone</dt><dd class="text-vert-sombre">{{ Telephone::lisible($c->telephone) ?: '–' }}</dd></div>
    <div><dt class="text-gris-texte">Âge le jour J</dt><dd class="text-vert-sombre">{{ Benevolat::AGES[$c->age] ?? '–' }}</dd></div>

    @if ($sensible)
        @if ($c->mineur())
            <div><dt class="text-gris-texte">Responsable légal</dt><dd class="text-vert-sombre">{{ $c->responsable_nom }}, {{ Telephone::lisible($c->responsable_tel) }}</dd></div>
        @endif
        <div><dt class="text-gris-texte">Contact d'urgence</dt><dd class="text-vert-sombre">{{ $c->urgence_nom }}, {{ Telephone::lisible($c->urgence_tel) }}{{ $c->urgence_lien ? ' ('.$c->urgence_lien.')' : '' }}</dd></div>
    @endif

    <div class="sm:col-span-2"><dt class="text-gris-texte">Missions préférées</dt><dd class="text-vert-sombre">{{ collect($c->missions_preferees)->map(fn ($m, $i) => ($i + 1).'. '.Benevolat::mission($m))->join(' · ') ?: '–' }}</dd></div>
    @if ($c->missions_impossibles)
        <div class="sm:col-span-2"><dt class="text-gris-texte">Missions impossibles</dt><dd class="text-vert-sombre">{{ collect($c->missions_impossibles)->map(fn ($m) => Benevolat::mission($m))->join(', ') }}</dd></div>
    @endif
    @if ($c->competences)
        <div><dt class="text-gris-texte">Communication</dt><dd class="text-vert-sombre">{{ collect($c->competences)->map(fn ($m) => Benevolat::COMPETENCES[$m] ?? $m)->join(', ') }}</dd></div>
    @endif
    @foreach (['experience' => 'Expérience', 'competences_detail' => 'Compétences', 'commentaire' => 'Commentaire'] as $champ => $libelle)
        @if ($c->{$champ})
            <div class="sm:col-span-2"><dt class="text-gris-texte">{{ $libelle }}</dt><dd class="whitespace-pre-line text-vert-sombre">{{ $c->{$champ} }}</dd></div>
        @endif
    @endforeach

    <div><dt class="text-gris-texte">Jour J</dt><dd class="text-vert-sombre">{{ ($dispos['toute_journee'] ?? false) ? 'Toute la journée' : (collect($dispos['tranches'] ?? [])->map(fn ($t) => Benevolat::TRANCHES[$t] ?? $t)->join(', ') ?: '–') }}</dd></div>
    @foreach (Benevolat::PERIODES as $cle => $p)
        @php($per = $dispos['periodes'][$cle] ?? null)
        <div><dt class="text-gris-texte">{{ $p['libelle'] }} ({{ $p['detail'] }})</dt><dd class="text-vert-sombre">{{ Benevolat::ETATS_PERIODE[$per['etat'] ?? ''] ?? '–' }}{{ ($per['debut'] ?? null) ? ', de '.$per['debut'] : '' }}{{ ($per['fin'] ?? null) ? ' à '.$per['fin'] : '' }}</dd></div>
    @endforeach
    <div><dt class="text-gris-texte">Véhicule</dt><dd class="text-vert-sombre">{{ $ouiNon($c->vehicule) }}{{ $c->vehicule ? ' · transport du matériel : '.mb_strtolower($ouiNon($c->transport)) : '' }}</dd></div>
    @if ($c->transport_detail)
        <div><dt class="text-gris-texte">Transport</dt><dd class="whitespace-pre-line text-vert-sombre">{{ $c->transport_detail }}</dd></div>
    @endif
    <div><dt class="text-gris-texte">Binôme souhaité</dt><dd class="text-vert-sombre">{{ $c->binome ?: 'Non' }}</dd></div>
    <div><dt class="text-gris-texte">Court aussi</dt><dd class="text-vert-sombre">{{ $c->court ? 'Oui'.($c->course ? ' · '.$c->course->nom.($c->course->heure_depart ? ', départ '.\Illuminate\Support\Carbon::parse($c->course->heure_depart)->format('G\hi') : '') : ', épreuve pas encore choisie') : 'Non' }}</dd></div>

    <div><dt class="text-gris-texte">Tee-shirt</dt><dd class="text-vert-sombre">{{ $c->taille_tshirt ?: '–' }}</dd></div>
    <div><dt class="text-gris-texte">Repas</dt><dd class="text-vert-sombre">{{ $ouiNon($c->repas) }}</dd></div>
    @if ($sensible && $c->repas)
        <div><dt class="text-gris-texte">Allergies, besoins alimentaires</dt><dd class="whitespace-pre-line text-vert-sombre">{{ $c->allergies }}</dd></div>
    @endif
    <div><dt class="text-gris-texte">Soirée après l'événement</dt><dd class="text-vert-sombre">{{ Benevolat::SOIREE[$c->soiree] ?? '–' }}</dd></div>
    <div><dt class="text-gris-texte">Droit à l'image</dt><dd class="text-vert-sombre">{{ $c->droit_image === null ? '–' : ($c->droit_image ? 'Accepté' : 'Refusé') }}{{ $c->texteDroitImage ? ' (texte v'.$c->texteDroitImage->version.')' : '' }}</dd></div>
</dl>
