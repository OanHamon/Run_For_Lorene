@props(['evenement'])

@php
    use App\Models\Evenement;

    // Une édition passée encore « Publié » est signalée : elle reste sur le site tant qu'on ne la clôture pas.
    $passe = $evenement->date_evenement->lt(today());
    [$couleur, $libelle] = match (true) {
        $evenement->statut === Evenement::CLOTURE => ['bg-vert-sombre text-white', 'Clôturé'],
        $evenement->statut === Evenement::PUBLIE && $passe => ['bg-moutarde/20 text-vert-sombre', 'Terminé, à clôturer'],
        $evenement->statut === Evenement::PUBLIE => ['bg-vert-clair text-vert', 'Publié'],
        default => ['bg-bordure text-gris-texte', 'Brouillon'],
    };
@endphp
<span {{ $attributes->class(['rfl-badge', $couleur]) }}>{{ $libelle }}</span>
