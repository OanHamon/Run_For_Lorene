@php use App\Support\ModelesRelance; @endphp
<x-admin-shell title="Relances">
    <x-admin.titre titre="Relances" :sous-titre="$evenement ? 'Emails aux inscrits · '.$evenement->nom : null" />

    @if (! $evenement)
        <div class="rfl-carte px-6 py-10 text-center text-gris-texte">Aucun événement pour l'instant.</div>
    @else
        {{-- 1. Démarrer : un gros bouton par message type, en français simple. --}}
        <section class="mb-8" aria-labelledby="titre-nouvelle">
            <h2 id="titre-nouvelle" class="font-display text-xl text-vert">Envoyer un message</h2>
            <p class="mt-1 mb-4 text-base text-gris-texte">Choisis le message à envoyer. Tout reste modifiable, et tu vérifies avant que rien ne parte.</p>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                @foreach ($modeles as $cle => $m)
                    <a href="{{ route('admin.relances.create', ['modele' => $cle]) }}" class="rfl-carte block p-5 hover:ring-2 hover:ring-vert" data-test="modele-{{ $cle }}">
                        <p class="text-lg font-bold text-vert">{{ $m['libelle'] }}</p>
                        <p class="mt-1 text-sm text-gris-texte">Pour : {{ mb_strtolower(ModelesRelance::QUI[$m['qui']]['libelle']) }}</p>
                    </a>
                @endforeach
            </div>
        </section>

        {{-- 2. Envois programmés --}}
        <section class="mb-8 rfl-carte p-6" aria-labelledby="titre-programmees">
            <h2 id="titre-programmees" class="font-display text-xl text-vert">Envois programmés</h2>
            <p class="mt-1 mb-4 text-base text-gris-texte">Les messages qui partiront tout seuls, à la date prévue. La liste des personnes est recalculée au moment de l'envoi.</p>

            @forelse ($programmees as $p)
                @php($etatP = $p->etat())
                <article class="mb-3 flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-fond p-4" data-test="programmee-{{ $p->id_programmee }}">
                    <div class="min-w-0">
                        <p class="text-base font-bold">{{ ModelesRelance::lisible($p->sujet, $evenement) }}</p>
                        <p class="text-sm">{{ $p->enClair() }} · pour {{ mb_strtolower(ModelesRelance::QUI[$p->cible['qui'] ?? 'tous']['libelle'] ?? '') }}</p>
                        <p class="mt-1 text-sm text-gris-texte">
                            @if ($etatP === 'terminee')
                                Terminé{{ $p->derniere_execution ? ' · dernier envoi le '.$p->derniere_execution->format('d/m à H:i') : '' }}
                            @elseif ($etatP === 'pause')
                                <span class="rfl-badge bg-moutarde text-vert-sombre">En pause</span> Rien ne part tant que tu ne relances pas.
                            @else
                                Prochain envoi : <strong class="text-vert-sombre">{{ $p->prochaine()->translatedFormat('l j F Y \à G\hi') }}</strong>
                            @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if ($etatP !== 'terminee')
                            <form method="POST" action="{{ route('admin.relances.programmees.pause', $p) }}">
                                @csrf
                                <button type="submit" class="rfl-btn-secondaire px-4 py-2 text-sm">{{ $p->actif ? 'Mettre en pause' : 'Reprendre' }}</button>
                            </form>
                            <a href="{{ route('admin.relances.programmees.edit', $p) }}" class="rfl-btn-secondaire px-4 py-2 text-sm">Modifier</a>
                        @endif
                        <form method="POST" action="{{ route('admin.relances.programmees.destroy', $p) }}" onsubmit="return confirm('Supprimer cet envoi programmé ? Les messages déjà partis restent dans l\'historique.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-4 py-2 text-sm text-erreur hover:underline">Supprimer</button>
                        </form>
                    </div>
                </article>
            @empty
                <p class="rounded-2xl bg-fond px-4 py-6 text-center text-base text-gris-texte">Aucun envoi programmé. Choisis un message ci-dessus, puis « Plus tard » à l'étape « Quand ? ».</p>
            @endforelse
        </section>

        {{-- 3. Relances automatiques du site --}}
        <section class="mb-8 rfl-carte p-6" aria-labelledby="titre-auto">
            <h2 id="titre-auto" class="font-display text-xl text-vert">Relance automatique du site</h2>
            <p class="mt-1 mb-4 text-base text-gris-texte">Une fois cochée, elle part toute seule, pour tous les événements. Chaque personne ne la reçoit qu'une fois. Pas de relance de paiement : l'inscription se fait d'une traite.</p>

            <form method="POST" action="{{ route('admin.relances.reglages') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <fieldset class="rounded-2xl bg-fond p-4">
                    <legend class="sr-only">Documents manquants</legend>
                    <label class="flex items-center gap-3 text-base font-bold">
                        <input type="checkbox" name="documents_actif" value="1" class="h-5 w-5" @checked(old('documents_actif', $documents['actif']))>
                        Documents manquants
                    </label>
                    <p class="mt-2 flex flex-wrap items-center gap-2 text-base">
                        Envoyer
                        <input type="number" name="documents_jours" min="1" max="90" value="{{ old('documents_jours', $documents['jours_avant']) }}" class="rfl-champ-compact w-20 text-center" aria-label="Nombre de jours avant l'événement">
                        jours avant l'événement, à
                        <input type="time" name="documents_heure" value="{{ old('documents_heure', $documents['heure']) }}" class="rfl-champ-compact w-32" aria-label="Heure d'envoi">
                    </p>
                    @error('documents_jours', 'reglages')<p class="rfl-erreur">{{ $message }}</p>@enderror
                    @error('documents_heure', 'reglages')<p class="rfl-erreur">{{ $message }}</p>@enderror
                </fieldset>

                <button type="submit" class="rfl-btn px-6 py-3">Enregistrer</button>
            </form>
        </section>

        {{-- 4. Historique --}}
        <section class="rfl-carte overflow-hidden" aria-labelledby="titre-historique">
            <h2 id="titre-historique" class="px-6 pt-5 font-display text-xl text-vert">Messages déjà envoyés</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="rfl-tableau min-w-[640px]">
                    <thead><tr><th>Date</th><th>Objet</th><th class="text-right">Personnes</th><th>Par</th></tr></thead>
                    <tbody>
                        @forelse ($relances as $r)
                            <tr>
                                <td class="whitespace-nowrap">{{ $r->created_at->format('d/m/Y H:i') }}</td>
                                <td class="font-bold">{{ ModelesRelance::lisible($r->sujet, $evenement) }}@if ($r->id_programmee) <span class="ml-1 rfl-badge bg-vert-clair text-vert">programmé</span>@endif</td>
                                <td class="text-right">{{ $r->nb_destinataires }}</td>
                                <td class="text-sm text-gris-texte">{{ $r->admin?->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-gris-texte">Aucun message envoyé pour l'instant.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4">{{ $relances->links() }}</div>
        </section>
    @endif
</x-admin-shell>
