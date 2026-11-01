@php
    use App\Support\Format;
    use Illuminate\Support\Carbon;

    $jour = fn (string $d) => Carbon::parse($d)->translatedFormat('j M');
@endphp
<x-admin-shell title="Statistiques">
    <x-slot:head>@vite('resources/js/admin-graphiques.js')</x-slot:head>

    <x-admin.titre titre="Statistiques" :sous-titre="$evenement?->nom" />

    @if (! $evenement)
        <div class="rfl-carte px-6 py-10 text-center text-gris-texte">Aucun événement pour l'instant.</div>
    @else
        <div class="grid gap-6 xl:grid-cols-2">
            {{-- 1. Rythme des inscriptions --}}
            <x-admin.graphique class="xl:col-span-2" titre="Inscriptions jour par jour" question="Est-ce qu'on avance au bon rythme ? Qu'est-ce qui a fait monter les inscriptions ?"
                :vide="! $cumul['jours']" hauteur="h-80"
                :config="['type' => 'bar', 'libelles' => array_map($jour, $cumul['jours']), 'axeDroit' => true, 'jalons' => array_map(fn ($j) => ['jour' => $jour($j['jour']), 'libelle' => $j['libelle']], $cumul['jalons']), 'series' => [
                    ['libelle' => 'Total cumulé', 'type' => 'line', 'valeurs' => $cumul['cumul'], 'couleur' => 'vert', 'axe' => 'y'],
                    ['libelle' => 'Nouvelles du jour', 'type' => 'bar', 'valeurs' => $cumul['parJour'], 'couleur' => 'vert-doux', 'axe' => 'y2'],
                ]]">
                <x-slot:valeurs>
                    <table class="rfl-tableau">
                        <thead><tr><th>Jour</th><th class="text-right">Nouvelles</th><th class="text-right">Total</th><th>Jalon</th></tr></thead>
                        <tbody>
                            @foreach ($cumul['jours'] as $n => $j)
                                @continue($cumul['parJour'][$n] === 0 && ! collect($cumul['jalons'])->contains('jour', $j))
                                <tr><td>{{ $jour($j) }}</td><td class="text-right">{{ $cumul['parJour'][$n] }}</td><td class="text-right">{{ $cumul['cumul'][$n] }}</td><td>{{ collect($cumul['jalons'])->where('jour', $j)->pluck('libelle')->join(', ') }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-slot:valeurs>
            </x-admin.graphique>

            {{-- 2. Remplissage et projection --}}
            <x-admin.graphique titre="Remplissage des courses" question="Faut-il ouvrir des places ? À ce rythme, quand la course sera-t-elle complète ?" :vide="! $remplissage">
                <ul class="space-y-4" data-test="remplissage">
                    @foreach ($remplissage as $r)
                        <li>
                            <div class="mb-1 flex flex-wrap justify-between gap-2 text-sm">
                                <span class="font-bold">{{ $r['course'] }}</span>
                                <span class="text-gris-texte">{{ $r['places'] }} / {{ $r['quota'] }} · {{ $r['pourcentage'] }} %</span>
                            </div>
                            <div class="h-3 overflow-hidden rounded-full bg-fond ring-1 ring-bordure" role="meter" aria-valuemin="0" aria-valuemax="{{ $r['quota'] }}" aria-valuenow="{{ $r['places'] }}" aria-label="Remplissage {{ $r['course'] }}">
                                <div class="h-full rounded-full {{ $r['pourcentage'] >= 100 ? 'bg-vert' : 'bg-vert-doux' }}" style="width: {{ $r['pourcentage'] }}%"></div>
                            </div>
                            <p class="mt-1 text-xs text-gris-texte">
                                @if ($r['complete_le'] === 'complète')
                                    Course complète.
                                @elseif ($r['complete_le'])
                                    {{ str_replace('.', ',', (string) $r['rythme']) }} inscription(s) par jour sur {{ \App\Services\Admin\Graphiques::RYTHME_JOURS }} jours : complète vers le <strong class="text-vert-sombre">{{ $r['complete_le'] }}</strong>.
                                @else
                                    Au rythme actuel, pas complète avant la fermeture.
                                @endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            </x-admin.graphique>

            {{-- 3. Entonnoir --}}
            <x-admin.graphique titre="Du début de l'inscription à la validation" question="Où est-ce qu'on perd des gens dans le tunnel ?" :vide="$entonnoir[0]['nombre'] === 0">
                @php($depart = max(1, $entonnoir[0]['nombre']))
                <ol class="space-y-3" data-test="entonnoir">
                    @foreach ($entonnoir as $n => $e)
                        <li>
                            <div class="mb-1 flex justify-between gap-2 text-sm">
                                <span class="font-bold">{{ $e['etape'] }}</span>
                                <span class="text-gris-texte">{{ $e['nombre'] }}@if ($n > 0) · {{ (int) round($e['nombre'] * 100 / $depart) }} %@endif</span>
                            </div>
                            <div class="h-3 overflow-hidden rounded-full bg-fond ring-1 ring-bordure">
                                <div class="h-full rounded-full {{ $n === count($entonnoir) - 1 ? 'bg-vert' : 'bg-vert-doux' }}" style="width: {{ (int) round($e['nombre'] * 100 / $depart) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ol>
                <p class="mt-4 text-xs text-gris-texte">Pourcentages calculés sur les inscriptions commencées. Une inscription non finalisée n'est pas relancée : elle se fait d'une traite.</p>
            </x-admin.graphique>

            {{-- 4. Recettes --}}
            <x-admin.graphique class="xl:col-span-2" titre="Recettes par semaine" question="Combien est encaissé chaque semaine, inscriptions et dons, et combien est rendu ?" :vide="! $recettes"
                :config="['type' => 'bar', 'empile' => true, 'euros' => true, 'libelles' => array_map(fn ($l) => 'Sem. du '.$jour($l['semaine']), $recettes), 'series' => [
                    ['libelle' => 'Inscriptions', 'valeurs' => array_column($recettes, 'inscriptions'), 'couleur' => 'vert'],
                    ['libelle' => 'Dons', 'valeurs' => array_column($recettes, 'dons'), 'couleur' => 'vert-doux'],
                    ['libelle' => 'Remboursements', 'type' => 'line', 'valeurs' => array_column($recettes, 'rembourse'), 'couleur' => 'moutarde'],
                ]]">
                <x-slot:valeurs>
                    <table class="rfl-tableau">
                        <thead><tr><th>Semaine du</th><th class="text-right">Inscriptions</th><th class="text-right">Dons</th><th class="text-right">Remboursés</th><th class="text-right">Frais Stripe</th></tr></thead>
                        <tbody>
                            @foreach ($recettes as $l)
                                <tr><td>{{ $jour($l['semaine']) }}</td><td class="text-right">{{ Format::euros($l['inscriptions']) }}</td><td class="text-right">{{ Format::euros($l['dons']) }}</td><td class="text-right">{{ Format::euros($l['rembourse']) }}</td><td class="text-right">{{ Format::euros($l['frais']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-slot:valeurs>
            </x-admin.graphique>

            {{-- 5. Âges et sexes --}}
            @php($sexes = array_values(\App\Services\Admin\Graphiques::SEXES))
            @php($couleursSexes = ['vert', 'vert-doux', 'moutarde', 'vert-clair'])
            <x-admin.graphique titre="Âge et sexe des inscrits" question="Qui vient courir ? Utile pour les catégories et la communication." :vide="array_sum(array_map('array_sum', $repartition['ages'])) === 0"
                :config="['type' => 'bar', 'horizontal' => true, 'empile' => true, 'libelles' => array_keys($repartition['ages']), 'series' => collect($sexes)->map(fn ($s, $n) => ['libelle' => $s, 'valeurs' => array_column($repartition['ages'], $s), 'couleur' => $couleursSexes[$n]])->filter(fn ($s) => array_sum($s['valeurs']) > 0)->values()->all()]">
                <x-slot:valeurs>
                    <table class="rfl-tableau">
                        <thead><tr><th>Âge</th>@foreach ($sexes as $s)<th class="text-right">{{ $s }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($repartition['ages'] as $tranche => $parSexe)
                                <tr><td>{{ $tranche }}</td>@foreach ($sexes as $s)<td class="text-right">{{ $parSexe[$s] }}</td>@endforeach</tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-slot:valeurs>
            </x-admin.graphique>

            {{-- 6. Départements --}}
            <x-admin.graphique titre="D'où viennent les inscrits" question="Par département : utile pour les partenariats locaux et les dossiers de subvention." :vide="! $departements"
                :config="['type' => 'bar', 'horizontal' => true, 'libelles' => array_map('strval', array_keys($departements)), 'series' => [['libelle' => 'Inscrits', 'valeurs' => array_values($departements), 'couleur' => 'vert-doux']]]">
                <x-slot:valeurs>
                    <table class="rfl-tableau">
                        <thead><tr><th>Département</th><th class="text-right">Inscrits</th></tr></thead>
                        <tbody>
                            @foreach ($departements as $d => $n)
                                <tr><td>{{ $d }}</td><td class="text-right">{{ $n }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-slot:valeurs>
            </x-admin.graphique>

            {{-- 7. Moments --}}
            <x-admin.graphique class="xl:col-span-2" titre="Quand les gens s'inscrivent" question="Le meilleur moment pour publier sur les réseaux ou envoyer une relance." :vide="array_sum(array_map('array_sum', $moments['valeurs'])) === 0">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] border-separate border-spacing-1 text-xs" data-test="moments">
                        <thead><tr><th class="sr-only">Jour</th>@foreach ($moments['tranches'] as $t)<th class="font-normal text-gris-texte">{{ $t }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($moments['jours'] as $n => $j)
                                <tr>
                                    <th class="pr-2 text-left font-normal text-gris-texte">{{ $j }}</th>
                                    @foreach ($moments['valeurs'][$n] as $v)
                                        @php($niveau = $v === 0 ? 0 : (int) ceil($v * 4 / $moments['max']))
                                        <td class="h-8 rounded-lg text-center {{ ['bg-fond text-gris-texte', 'bg-vert-clair text-vert-sombre', 'bg-vert-doux text-white', 'bg-vert text-white', 'bg-vert-sombre text-white'][$niveau] }}" title="{{ $j }}, {{ $moments['tranches'][$loop->index] }} : {{ $v }}">{{ $v ?: '' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.graphique>

            {{-- Inscrits par course (complète l'âge) --}}
            <x-admin.graphique titre="Inscrits par course" question="Comment les inscrits se répartissent entre les épreuves." :vide="! $repartition['courses']"
                :config="['type' => 'bar', 'libelles' => array_keys($repartition['courses']), 'series' => [['libelle' => 'Inscrits', 'valeurs' => array_values($repartition['courses']), 'couleur' => 'vert']]]">
                <x-slot:valeurs>
                    <table class="rfl-tableau">
                        <thead><tr><th>Course</th><th class="text-right">Inscrits</th></tr></thead>
                        <tbody>
                            @foreach ($repartition['courses'] as $c => $n)
                                <tr><td>{{ $c }}</td><td class="text-right">{{ $n }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-slot:valeurs>
            </x-admin.graphique>
        </div>
    @endif
</x-admin-shell>
