<x-admin-shell title="À traiter">
    <x-admin.titre titre="À traiter" :sous-titre="$evenement ? 'Tout ce qui attend une action · '.$evenement->nom : 'Tout ce qui attend une action'" />

    @if (! $groupes)
        <div class="rfl-carte px-6 py-12 text-center">
            <p class="font-display text-xl text-vert">Rien à traiter</p>
            <p class="mt-1 text-sm text-gris-texte">Les paiements, dossiers et dossards sont à jour.</p>
        </div>
    @endif

    <div class="space-y-6">
        @foreach ($groupes as $g)
            <section class="rfl-carte overflow-hidden" data-test="groupe-{{ $g['cle'] }}" aria-labelledby="groupe-{{ $g['cle'] }}">
                <div class="flex flex-wrap items-start justify-between gap-3 px-6 pt-5 pb-3">
                    <div class="min-w-0">
                        <h2 id="groupe-{{ $g['cle'] }}" class="font-display text-lg text-vert">{{ $g['titre'] }}</h2>
                        <p class="mt-0.5 text-sm text-gris-texte">{{ $g['aide'] }}</p>
                    </div>
                    <span class="rfl-badge bg-vert text-white">{{ $g['nombre'] }}</span>
                </div>
                <ul class="divide-y divide-bordure border-t border-bordure">
                    @foreach ($g['elements'] as $e)
                        <li class="relative flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-6 py-3 hover:bg-fond/60">
                            <div class="min-w-0">
                                @if ($e['url'])
                                    <a href="{{ $e['url'] }}" class="font-bold after:absolute after:inset-0">{{ $e['libelle'] }}</a>
                                @else
                                    <span class="font-bold">{{ $e['libelle'] }}</span>
                                @endif
                                @if ($e['detail'])<p class="text-sm text-gris-texte">{{ $e['detail'] }}</p>@endif
                            </div>
                            @if ($e['url'])<span class="text-sm text-vert" aria-hidden="true">Ouvrir ›</span>@endif
                        </li>
                    @endforeach
                </ul>
                @if ($g['voir_tout'])
                    <p class="border-t border-bordure px-6 py-3 text-sm"><a href="{{ $g['voir_tout'] }}" class="rfl-lien">Voir les {{ $g['nombre'] }}</a></p>
                @endif
            </section>
        @endforeach
    </div>
</x-admin-shell>
