{{-- Une inscription à l'écran de retrait : nom, course, dossard, mineur, et l'action. --}}
@php
    $remis = $i->date_retrait !== null;
    $estMineur = $i->participant->ageAu($ecran['evenement']->date_evenement) < 18;
@endphp
<article class="rounded-3xl p-5 ring-1 {{ $grande ? 'bg-fond ring-vert' : 'bg-white ring-bordure' }}" data-test="fiche-retrait-{{ $i->id_inscription }}">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="font-display {{ $grande ? 'text-2xl' : 'text-lg' }} text-vert">{{ $i->participant->user->name }}</p>
            <p class="text-sm text-vert-sombre">{{ $i->tarif->course->nom }}</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @if ($estMineur)<span class="rfl-badge bg-moutarde text-vert-sombre">Mineur · {{ $i->participant->ageAu($ecran['evenement']->date_evenement) }} ans</span>@endif
                @if ($remis)
                    <span class="rfl-badge bg-vert-clair text-vert">Déjà remis</span>
                @elseif ($i->statut !== \App\Models\Inscription::VALIDEE)
                    <span class="rfl-badge bg-erreur-fond text-erreur">Dossier incomplet</span>
                @endif
            </div>
        </div>
        @if ($i->dossard)
            <div class="rounded-2xl bg-vert px-4 py-2 text-center">
                <p class="text-xs text-white/85">Dossard</p>
                <p class="font-display text-3xl text-moutarde">{{ $i->dossard }}</p>
            </div>
        @endif
    </div>

    @if ($i->statut === \App\Models\Inscription::VALIDEE && $i->dossard)
        @if (! $remis)
            <form method="POST" action="{{ $ecran['routes']['remettre']($i) }}" class="mt-4" data-envoi-unique>
                @csrf
                <button type="submit" class="rfl-btn w-full py-4 text-lg">Remettre le dossard</button>
            </form>
        @else
            <form method="POST" action="{{ $ecran['routes']['annuler']($i) }}" class="mt-3" onsubmit="return confirm('Annuler la remise du dossard {{ $i->dossard }} ? À faire seulement en cas d\'erreur.')">
                @csrf
                <button type="submit" class="text-sm rfl-lien">Erreur ? Annuler la remise</button>
            </form>
        @endif
    @endif
</article>
