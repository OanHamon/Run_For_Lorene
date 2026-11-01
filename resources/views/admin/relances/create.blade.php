@php
    use App\Models\Inscription;
    use App\Support\ModelesRelance;

    $parametres = fn (string $m) => array_filter(['modele' => $m, 'inscription' => $inscription?->id_inscription]);
@endphp
<x-admin-shell title="Nouvelle relance">
    <x-admin.titre titre="Nouvelle relance" :sous-titre="$evenement->nom" />

    {{-- Modèles : prêts à l'emploi, tout reste modifiable avant l'envoi. --}}
    <nav class="mb-6 flex flex-wrap gap-2 text-sm" aria-label="Modèles">
        @foreach ($modeles as $m => $def)
            <a href="{{ route('admin.relances.create', $parametres($m)) }}" @if ($m === $cle) aria-current="true" @endif class="rounded-full px-4 py-2 {{ $m === $cle ? 'bg-vert font-bold text-white' : 'bg-white ring-1 ring-bordure hover:bg-fond' }}">{{ $def['libelle'] }}</a>
        @endforeach
    </nav>

    <form method="POST" action="{{ route('admin.relances.apercu') }}" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        @csrf
        <input type="hidden" name="modele" value="{{ $cle }}">

        <div class="rfl-carte space-y-5 p-6">
            <h2 class="font-display text-lg text-vert">Message</h2>
            <x-admin.champ name="sujet" label="Objet" :value="$sujet" required maxlength="150" />
            <div>
                <label for="message" class="rfl-label">Message</label>
                <textarea id="message" name="message" rows="12" required maxlength="5000" class="rfl-champ-compact h-auto min-h-64 font-sans leading-relaxed">{{ old('message', $message) }}</textarea>
                @error('message')<p class="rfl-erreur">{{ $message }}</p>@enderror
                <p class="mt-2 text-xs text-gris-texte">Une ligne vide sépare deux paragraphes. Le bouton « Ouvrir mon espace » est ajouté à la fin.</p>
            </div>
            <details class="text-sm">
                <summary class="cursor-pointer text-vert hover:underline">Variables disponibles</summary>
                <ul class="mt-2 grid gap-1 sm:grid-cols-2">
                    @foreach (ModelesRelance::VARIABLES as $v => $aide)
                        <li><code class="rounded bg-fond px-1.5 py-0.5 text-vert-sombre">{{ $v }}</code> <span class="text-gris-texte">{{ $aide }}</span></li>
                    @endforeach
                </ul>
            </details>
        </div>

        <div class="space-y-6">
            <div class="rfl-carte space-y-4 p-6">
                <h2 class="font-display text-lg text-vert">Destinataires</h2>
                @if ($inscription)
                    <input type="hidden" name="inscription" value="{{ $inscription->id_inscription }}">
                    <p class="rounded-2xl bg-fond px-4 py-3 text-sm">
                        Une seule personne : <strong>{{ $inscription->participant->user->name }}</strong> · {{ $inscription->tarif->course->nom }}
                    </p>
                    <a href="{{ route('admin.relances.create', ['modele' => $cle]) }}" class="text-sm rfl-lien">Écrire plutôt à un groupe</a>
                @else
                    <div>
                        <label for="statut" class="rfl-label">Statut</label>
                        <select id="statut" name="statut" class="rfl-champ-compact">
                            <option value="">Toutes les inscriptions réglées</option>
                            @foreach (\App\Services\Admin\RelancesCiblees::STATUTS as $s)
                                <option value="{{ $s }}" @selected($cible['statut'] === $s)>{{ Inscription::LIBELLES[$s] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="course" class="rfl-label">Course</label>
                        <select id="course" name="course" class="rfl-champ-compact">
                            <option value="">Toutes les courses</option>
                            @foreach ($courses as $c)
                                <option value="{{ $c->id_course }}" @selected($cible['course'] === $c->id_course)>{{ $c->nom }}</option>
                            @endforeach
                        </select>
                    </div>
                    <fieldset class="space-y-2 text-sm">
                        <legend class="sr-only">Filtres</legend>
                        <label class="flex items-center gap-2"><input type="checkbox" name="mineur" value="1" @checked($cible['mineur'])> Mineurs seulement</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="documents" value="1" @checked($cible['documents'])> Documents manquants</label>
                        <label class="flex items-center gap-2"><input type="checkbox" name="non_retire" value="1" @checked($cible['non_retire'])> Dossard pas encore retiré</label>
                    </fieldset>
                    <p class="text-xs text-gris-texte">Une inscription non finalisée (paiement en attente) n'est jamais relancée. Pour écrire à une seule personne, pars de sa fiche d'inscription.</p>
                @endif
                <button type="submit" class="rfl-btn-secondaire w-full px-5 py-2.5 text-sm">Voir l'aperçu</button>
            </div>
        </div>
    </form>

    @if ($apercu)
        <section class="mt-6 rfl-carte p-6" aria-labelledby="apercu" data-test="apercu">
            <h2 id="apercu" class="font-display text-lg text-vert">Aperçu</h2>

            @if ($destinataires->isEmpty())
                <p class="mt-2 rfl-alerte-info text-sm">Personne ne correspond à cette cible.</p>
            @else
                <p class="mt-1 text-sm text-gris-texte">
                    <strong class="text-vert-sombre" data-test="nb-destinataires">{{ $destinataires->count() }}</strong> destinataire(s). Exemple pour {{ $exemple['destinataire']->name }} ({{ $exemple['destinataire']->email }}) :
                </p>
                <div class="mt-4 rounded-2xl bg-fond p-5 text-sm">
                    <p class="font-bold">{{ $exemple['sujet'] }}</p>
                    <div class="mt-3 space-y-3 whitespace-pre-line">{{ $exemple['message'] }}</div>
                </div>

                @if ($recents->isNotEmpty())
                    <div class="mt-4 rfl-alerte text-sm" role="alert">
                        <p class="font-bold">{{ $recents->count() }} personne(s) ont déjà reçu une relance il y a moins de {{ \App\Services\Admin\RelancesCiblees::DELAI_HEURES }} h.</p>
                        <p class="mt-1 text-gris-texte">{{ $recents->take(8)->map(fn ($i) => $i->participant->user->name)->join(', ') }}{{ $recents->count() > 8 ? '…' : '' }}. Elles sont écartées, sauf si tu coches la case ci-dessous.</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.relances.store') }}" class="mt-6 space-y-3" data-envoi-unique>
                    @csrf
                    @foreach (['modele' => $cle, 'sujet' => $sujet, 'message' => $message, 'inscription' => $cible['inscription'], 'statut' => $cible['statut'], 'course' => $cible['course'], 'mineur' => $cible['mineur'] ? 1 : null, 'documents' => $cible['documents'] ? 1 : null, 'non_retire' => $cible['non_retire'] ? 1 : null] as $nom => $valeur)
                        @if ($valeur !== null && $valeur !== '')<input type="hidden" name="{{ $nom }}" value="{{ $valeur }}">@endif
                    @endforeach
                    @if ($recents->isNotEmpty())
                        <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="inclure_recents" value="1" class="mt-1"> Envoyer aussi aux personnes déjà relancées</label>
                    @endif
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="confirmation" value="1" class="mt-1"> J'ai relu le message : l'envoyer à {{ $destinataires->count() - $recents->count() }} personne(s){{ $recents->isNotEmpty() ? ' (ou '.$destinataires->count().' en incluant les déjà relancées)' : '' }}.</label>
                    @error('confirmation')<p class="rfl-erreur">{{ $message }}</p>@enderror
                    <button type="submit" class="rfl-btn px-6 py-3">Envoyer</button>
                </form>
            @endif
        </section>
    @endif
</x-admin-shell>
