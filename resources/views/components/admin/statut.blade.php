@props(['inscription'])

@php
    use App\Models\Inscription;

    $couleur = match ($inscription->statut) {
        Inscription::VALIDEE => 'bg-vert-clair text-vert',
        Inscription::DOSSIER_INCOMPLET, Inscription::EN_ATTENTE_PAIEMENT => 'bg-moutarde/20 text-vert-sombre',
        default => 'bg-bordure text-gris-texte',
    };
@endphp
<span {{ $attributes->class(['rfl-badge', $couleur]) }}>{{ $inscription->libelleStatut() }}</span>
