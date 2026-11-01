<x-admin-shell title="État technique">
    <x-admin.titre titre="État technique" sous-titre="Pour repérer une panne avant les participants." />

    @php($pastille = fn (bool $ok) => $ok ? 'bg-vert-clair text-vert' : 'bg-erreur-fond text-erreur')

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rfl-carte p-6" aria-labelledby="titre-file">
            <h2 id="titre-file" class="font-display text-lg text-vert">Emails en file d'attente</h2>
            <p class="mt-0.5 mb-4 text-sm text-gris-texte">Les emails partent par la file. Des emails qui attendent depuis longtemps : le worker (queue:work) est arrêté.</p>
            @php($bloque = $file['plus_vieux'] && $file['plus_vieux']->lt(now()->subMinutes(10)))
            <dl class="grid grid-cols-2 gap-4 text-sm">
                <div class="rounded-2xl bg-fond p-4"><dt class="text-gris-texte">En attente</dt><dd class="font-display text-2xl text-vert" data-test="file-attente">{{ $file['en_attente'] }}</dd>
                    @if ($file['plus_vieux'])<p class="mt-1 text-xs {{ $bloque ? 'font-bold text-erreur' : 'text-gris-texte' }}">le plus ancien : {{ $file['plus_vieux']->diffForHumans() }}</p>@endif
                </div>
                <div class="rounded-2xl bg-fond p-4"><dt class="text-gris-texte">En échec</dt><dd class="font-display text-2xl {{ $file['echecs'] ? 'text-erreur' : 'text-vert' }}" data-test="file-echecs">{{ $file['echecs'] }}</dd></div>
            </dl>
            @if ($file['derniers_echecs']->isNotEmpty())
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($file['derniers_echecs'] as $e)
                        <li class="rounded-2xl border border-erreur-bord bg-erreur-fond px-4 py-2"><strong>{{ class_basename($e->tache) }}</strong> · {{ $e->le->format('d/m H:i') }}<br><span class="text-xs">{{ $e->erreur }}</span></li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs text-gris-texte">Pour les renvoyer, sur le serveur : <code>php artisan queue:retry all</code>.</p>
            @endif
        </section>

        <section class="rfl-carte p-6" aria-labelledby="titre-taches">
            <h2 id="titre-taches" class="font-display text-lg text-vert">Tâches automatiques</h2>
            <p class="mt-0.5 mb-4 text-sm text-gris-texte">Purge RGPD et relances automatiques, lancées par le planificateur (schedule:run chaque minute).</p>
            <table class="rfl-tableau">
                <thead><tr><th>Tâche</th><th>Fréquence</th><th>Dernière exécution</th></tr></thead>
                <tbody>
                    @foreach ($taches as $t)
                        <tr>
                            <td class="font-mono text-xs">{{ $t->nom }}</td>
                            <td class="font-mono text-xs">{{ $t->frequence }}</td>
                            <td class="text-sm">
                                @if ($t->derniere)
                                    <span class="rfl-badge {{ $pastille($t->derniere['reussie']) }}">{{ $t->derniere['reussie'] ? 'OK' : 'Échec' }}</span>
                                    {{ \Illuminate\Support\Carbon::parse($t->derniere['le'])->format('d/m H:i') }}
                                @else
                                    <span class="text-gris-texte">jamais vue</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="rfl-carte p-6 xl:col-span-2" aria-labelledby="titre-reglages">
            <h2 id="titre-reglages" class="font-display text-lg text-vert">Réglages</h2>
            <ul class="mt-4 divide-y divide-bordure text-sm" data-test="reglages">
                @foreach ($reglages as $r)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-2.5">
                        <span>{{ $r['libelle'] }}@unless ($r['ok'])<span class="block text-xs text-erreur">{{ $r['aide'] }}</span>@endunless</span>
                        <span class="rfl-badge {{ $pastille($r['ok']) }}">{{ $r['valeur'] }}</span>
                    </li>
                @endforeach
            </ul>
            @if ($paiements)
                <p class="mt-4 rfl-alerte text-sm">{{ $paiements }} paiement(s) à vérifier : <a href="{{ route('admin.a-traiter') }}" class="rfl-lien">voir « À traiter »</a>.</p>
            @endif
        </section>
    </div>
</x-admin-shell>
