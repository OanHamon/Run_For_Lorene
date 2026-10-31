<x-admin-shell title="Retrait des dossards">
    <x-slot:head>@vite('resources/js/retrait.js')</x-slot:head>
    <x-admin.titre titre="Retrait des dossards" :sous-titre="$evenement->nom.' · '.$evenement->date_evenement->translatedFormat('j F Y')" />

    <div class="grid gap-6 xl:grid-cols-[minmax(0,32rem)_minmax(0,1fr)] xl:items-start">
        @include('retrait._ecran', ['ecran' => $ecran])

        {{-- Postes des bénévoles --}}
        <section class="rfl-carte p-6" aria-labelledby="titre-postes">
            <h2 id="titre-postes" class="font-display text-lg text-vert">Postes des bénévoles</h2>
            <p class="mt-1 text-sm text-gris-texte">Un lien par poste, sans compte : il n'ouvre que cet écran, aux dates choisies. Le bénévole donne son prénom en l'ouvrant. Ferme le poste à la fin de la journée ou si le lien circule.</p>

            @if ($lien = session('retrait.lien'))
                <div class="mt-4 rounded-2xl border border-moutarde/40 bg-moutarde/10 p-4 text-sm" role="status">
                    <p class="font-bold">Lien du poste « {{ $lien['poste'] }} » : copie-le maintenant, il ne sera plus affiché.</p>
                    <input type="text" readonly value="{{ $lien['url'] }}" onclick="this.select()" class="mt-2 rfl-champ-compact font-mono text-xs" aria-label="Lien du poste">
                    <div class="mt-3 w-fit rounded-2xl bg-white p-2" aria-label="QR code du lien du poste">{!! \App\Support\Billet::qr($lien['url'], 160) !!}</div>
                    <p class="mt-2 text-xs text-gris-texte">Les bénévoles peuvent scanner ce QR code pour ouvrir le poste sur leur téléphone.</p>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.retrait.postes.store') }}" class="mt-5 grid gap-4 sm:grid-cols-3 sm:items-end">
                @csrf
                <x-admin.champ name="libelle" label="Nom du poste" sac="poste" placeholder="Entrée" required maxlength="60" />
                <x-admin.champ name="valable_du" label="Du" type="date" sac="poste" :value="$evenement->date_evenement->toDateString()" required />
                <x-admin.champ name="valable_au" label="Au" type="date" sac="poste" :value="$evenement->date_evenement->toDateString()" required />
                <div class="sm:col-span-3"><button type="submit" class="rfl-btn px-5 py-2.5 text-sm">Ouvrir un poste</button></div>
            </form>

            @if ($postes->isNotEmpty())
                <div class="mt-6 overflow-x-auto">
                    <table class="rfl-tableau min-w-[480px]">
                        <thead><tr><th>Poste</th><th>Dates</th><th class="text-right">Remises</th><th>État</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($postes as $p)
                                <tr>
                                    <td class="font-bold">{{ $p->libelle }}</td>
                                    <td class="text-sm">{{ $p->valable_du->format('d/m') }}{{ $p->valable_au->isSameDay($p->valable_du) ? '' : ' → '.$p->valable_au->format('d/m') }}</td>
                                    <td class="text-right">{{ $p->remises_count }}</td>
                                    <td class="text-sm">{{ $p->revoque_le ? 'Fermé' : ($p->utilisable() ? 'Ouvert' : 'Hors dates') }}</td>
                                    <td class="text-right">
                                        @unless ($p->revoque_le)
                                            <form method="POST" action="{{ route('admin.retrait.postes.revoquer', $p) }}" onsubmit="return confirm('Fermer le poste « {{ addslashes($p->libelle) }} » ? Son lien ne fonctionnera plus.')">
                                                @csrf
                                                <button type="submit" class="text-sm rfl-lien">Fermer</button>
                                            </form>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-admin-shell>
