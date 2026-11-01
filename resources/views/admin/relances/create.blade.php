@php
    use App\Support\ModelesRelance;

    $titre = $programmee ? 'Modifier l\'envoi programmé' : 'Nouveau message';
    // Lien qui garde ce qui est déjà choisi (le modèle remplace objet et message).
    $versModele = fn (string $m) => $programmee
        ? route('admin.relances.programmees.edit', [$programmee, 'modele' => $m])
        : route('admin.relances.create', array_filter(['modele' => $m, 'inscription' => $etat['qui'] === 'personne' ? $etat['inscription'] : null]));
    $etape = fn (int $n, string $libelle) => '<span class="mr-2 inline-flex h-8 w-8 items-center justify-center rounded-full bg-vert font-display text-base text-white">'.$n.'</span>'.e($libelle);
@endphp
<x-admin-shell :title="$titre">
    <x-admin.titre :titre="$titre" :sous-titre="$evenement->nom" />

    {{-- Étape 1 : quel message ? --}}
    <section class="mb-6 rfl-carte p-6" aria-labelledby="etape-1">
        <h2 id="etape-1" class="flex items-center font-display text-xl text-vert">{!! $etape(1, 'Quel message ?') !!}</h2>
        <p class="mt-2 mb-4 text-base text-gris-texte">Un modèle est prêt pour chaque cas. Tu pourras changer le texte à l'étape 3.</p>
        <nav class="flex flex-wrap gap-2" aria-label="Modèles">
            @foreach ($modeles as $m => $def)
                <a href="{{ $versModele($m) }}" @if ($m === $etat['modele']) aria-current="true" @endif class="rounded-full px-5 py-2.5 text-base {{ $m === $etat['modele'] ? 'bg-vert font-bold text-white' : 'bg-white ring-1 ring-bordure hover:bg-fond' }}">{{ $def['libelle'] }}</a>
            @endforeach
        </nav>
    </section>

    <form method="POST" action="{{ route('admin.relances.apercu') }}" class="space-y-6" novalidate>
        @csrf
        <input type="hidden" name="modele" value="{{ $etat['modele'] }}">
        @if ($programmee)<input type="hidden" name="programmee" value="{{ $programmee->id_programmee }}">@endif

        {{-- Étape 2 : à qui ? --}}
        <section class="group/qui rfl-carte p-6" aria-labelledby="etape-2">
            <h2 id="etape-2" class="flex items-center font-display text-xl text-vert">{!! $etape(2, 'À qui ?') !!}</h2>
            <fieldset class="mt-4 grid gap-3 lg:grid-cols-2">
                <legend class="sr-only">Destinataires</legend>
                @foreach (ModelesRelance::QUI as $q => $def)
                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl p-4 ring-1 ring-bordure has-[:checked]:bg-vert-clair has-[:checked]:ring-2 has-[:checked]:ring-vert">
                        <input type="radio" name="qui" value="{{ $q }}" id="qui-{{ $q }}" class="mt-1 h-5 w-5" @checked($etat['qui'] === $q)>
                        <span>
                            <span class="block text-base font-bold">{{ $def['libelle'] }}</span>
                            <span class="block text-sm text-gris-texte">{{ $def['aide'] }}</span>
                        </span>
                    </label>
                @endforeach
            </fieldset>

            {{-- Une seule personne : recherche par le nom (le formulaire repart en GET, sans rien perdre). --}}
            <div class="mt-4 {{ $etat['qui'] === 'personne' || $errors->has('inscription') ? '' : 'hidden' }} group-has-[#qui-personne:checked]/qui:block rounded-2xl bg-fond p-4">
                @if ($personne)
                    <input type="hidden" name="inscription" value="{{ $personne->id_inscription }}">
                    <p class="text-base">Personne choisie : <strong>{{ $personne->participant->user->name }}</strong> · {{ $personne->tarif->course->nom }}</p>
                @endif
                <label for="chercher" class="rfl-label mt-2">{{ $personne ? 'Choisir quelqu\'un d\'autre' : 'Nom de la personne' }}</label>
                <div class="flex flex-wrap gap-2">
                    <input id="chercher" name="chercher" type="search" value="{{ $etat['chercher'] }}" placeholder="Martin" class="rfl-champ max-w-sm">
                    <button type="submit" formmethod="GET" formaction="{{ $programmee ? route('admin.relances.programmees.edit', $programmee) : route('admin.relances.create') }}" class="rfl-btn-secondaire px-5 py-3">Chercher</button>
                </div>
                @if ($etat['chercher'] !== '')
                    <div class="mt-3 space-y-2" data-test="personnes-trouvees">
                        @forelse ($trouvees as $t)
                            <label class="flex cursor-pointer items-center gap-3 rounded-2xl bg-white p-3 ring-1 ring-bordure has-[:checked]:ring-2 has-[:checked]:ring-vert">
                                <input type="radio" name="inscription" value="{{ $t->id_inscription }}" class="h-5 w-5" @checked($personne?->id_inscription === $t->id_inscription)>
                                <span class="text-base"><strong>{{ $t->participant->user->name }}</strong> · {{ $t->tarif->course->nom }}</span>
                            </label>
                        @empty
                            <p class="text-sm text-gris-texte">Personne trouvée avec ce nom. Vérifie l'orthographe.</p>
                        @endforelse
                    </div>
                @endif
                @error('inscription')<p class="rfl-erreur">{{ $message }}</p>@enderror
            </div>

            <div class="mt-4 max-w-sm group-has-[#qui-personne:checked]/qui:hidden">
                <label for="course" class="rfl-label">Pour quelle course ?</label>
                <select id="course" name="course" class="rfl-champ">
                    <option value="">Toutes les courses</option>
                    @foreach ($courses as $c)
                        <option value="{{ $c->id_course }}" @selected($etat['course'] === $c->id_course)>{{ $c->nom }}</option>
                    @endforeach
                </select>
            </div>
        </section>

        {{-- Étape 3 : le message --}}
        <section class="rfl-carte p-6" aria-labelledby="etape-3">
            <h2 id="etape-3" class="flex items-center font-display text-xl text-vert">{!! $etape(3, 'Le message') !!}</h2>
            <div class="mt-4 space-y-4">
                <div>
                    <label for="sujet" class="rfl-label">Objet de l'email</label>
                    <input id="sujet" name="sujet" type="text" required maxlength="150" value="{{ $etat['sujet'] }}" class="rfl-champ">
                    @error('sujet')<p class="rfl-erreur">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="message" class="rfl-label">Message</label>
                    <p class="mb-2 text-sm text-gris-texte">Clique sur un bouton pour ajouter une information qui changera pour chaque personne :</p>
                    <div class="mb-2 flex flex-wrap gap-2" data-inserer-dans="message">
                        @foreach (ModelesRelance::INSERER as $variable => $libelle)
                            <button type="button" data-inserer="{{ $variable }}" class="rounded-full bg-vert-clair px-3 py-1.5 text-sm font-bold text-vert hover:bg-vert hover:text-white">+ {{ $libelle }}</button>
                        @endforeach
                    </div>
                    <textarea id="message" name="message" rows="12" required maxlength="5000" class="rfl-champ h-auto min-h-64 text-base leading-relaxed">{{ $etat['message'] }}</textarea>
                    @error('message')<p class="rfl-erreur">{{ $message }}</p>@enderror
                    <p class="mt-2 text-sm text-gris-texte">Laisse une ligne vide entre deux paragraphes. Le bouton « Ouvrir mon espace » est ajouté à la fin de l'email.</p>
                </div>
            </div>
        </section>

        {{-- Étape 4 : quand ? --}}
        <section class="group/quand rfl-carte p-6" aria-labelledby="etape-4">
            <h2 id="etape-4" class="flex items-center font-display text-xl text-vert">{!! $etape(4, 'Quand ?') !!}</h2>
            @php($choix = ['maintenant' => ['Tout de suite', 'Juste après ta vérification.'], 'date' => ['Un jour précis', 'Par exemple le 20 mars à 10h00.'], 'repetee' => ['Régulièrement', 'Par exemple tous les 7 jours, jusqu\'à une date.']])
            <fieldset class="mt-4 grid gap-3 sm:grid-cols-3">
                <legend class="sr-only">Moment de l'envoi</legend>
                @foreach ($choix as $q => [$libelle, $aide])
                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl p-4 ring-1 ring-bordure has-[:checked]:bg-vert-clair has-[:checked]:ring-2 has-[:checked]:ring-vert">
                        <input type="radio" name="quand" value="{{ $q }}" id="quand-{{ $q }}" class="mt-1 h-5 w-5" @checked($etat['quand'] === $q)>
                        <span><span class="block text-base font-bold">{{ $libelle }}</span><span class="block text-sm text-gris-texte">{{ $aide }}</span></span>
                    </label>
                @endforeach
            </fieldset>

            <div class="mt-4 flex flex-wrap items-end gap-4 text-base">
                <div class="hidden group-has-[#quand-date:checked]/quand:block">
                    <label for="date_jour" class="rfl-label">Le</label>
                    <input id="date_jour" name="date_jour" type="date" value="{{ $etat['date_jour'] }}" min="{{ today()->toDateString() }}" max="{{ $evenement->date_evenement->toDateString() }}" class="rfl-champ">
                </div>
                <div class="hidden group-has-[#quand-repetee:checked]/quand:block">
                    <label for="tous_les" class="rfl-label">Tous les combien de jours ?</label>
                    <input id="tous_les" name="tous_les" type="number" min="1" max="60" value="{{ $etat['tous_les'] }}" class="rfl-champ w-28 text-center">
                </div>
                <div class="hidden group-has-[#quand-repetee:checked]/quand:block">
                    <label for="repetee_du" class="rfl-label">À partir du</label>
                    <input id="repetee_du" name="repetee_du" type="date" value="{{ $etat['repetee_du'] }}" min="{{ today()->toDateString() }}" class="rfl-champ">
                </div>
                <div class="hidden group-has-[#quand-repetee:checked]/quand:block">
                    <label for="repetee_au" class="rfl-label">Jusqu'au</label>
                    <input id="repetee_au" name="repetee_au" type="date" value="{{ $etat['repetee_au'] }}" max="{{ $evenement->date_evenement->toDateString() }}" class="rfl-champ">
                </div>
                <div class="hidden group-has-[#quand-date:checked]/quand:block group-has-[#quand-repetee:checked]/quand:block">
                    <label for="heure" class="rfl-label">À quelle heure ?</label>
                    <input id="heure" name="heure" type="time" value="{{ $etat['heure'] }}" class="rfl-champ w-36">
                </div>
            </div>
            @foreach (['quand', 'date_jour', 'heure', 'tous_les', 'repetee_du', 'repetee_au'] as $champ)
                @error($champ)<p class="rfl-erreur">{{ $message }}</p>@enderror
            @endforeach
        </section>

        <div class="flex flex-wrap items-center gap-4">
            <button type="submit" class="rfl-btn px-8 py-4 text-lg">Vérifier avant d'envoyer</button>
            <a href="{{ route('admin.relances.index') }}" class="text-base rfl-lien">Annuler</a>
        </div>
    </form>

    @if ($apercu)
        <section id="verification" class="mt-6 rounded-[28px] bg-white p-6 ring-2 ring-vert" aria-labelledby="titre-verification" data-test="apercu">
            <h2 id="titre-verification" class="font-display text-xl text-vert">Vérifie avant d'envoyer</h2>

            @if ($destinataires->isEmpty())
                <p class="mt-3 rfl-alerte-info text-base">Personne ne correspond pour l'instant.@if ($etat['quand'] !== 'maintenant') Tu peux quand même programmer l'envoi : la liste sera recalculée le jour venu.@endif</p>
            @else
                <p class="mt-3 text-lg" data-test="phrase-envoi">
                    Ce message partira <strong>{{ $quandEnClair }}</strong> à <strong data-test="nb-destinataires">{{ $destinataires->count() - $recents->count() }}</strong> personne(s){{ $etat['quand'] !== 'maintenant' ? ' (nombre d\'aujourd\'hui, recalculé au moment de l\'envoi)' : '' }}.
                </p>
                <p class="mt-1 text-sm text-gris-texte">Exemple, tel que le recevra {{ $exemple['destinataire']->name }} ({{ $exemple['destinataire']->email }}) :</p>
                <div class="mt-3 rounded-2xl bg-fond p-5 text-base">
                    <p class="font-bold">{{ $exemple['sujet'] }}</p>
                    <div class="mt-3 whitespace-pre-line">{{ $exemple['message'] }}</div>
                </div>

                @if ($recents->isNotEmpty())
                    <div class="mt-4 rfl-alerte text-base" role="alert">
                        <p class="font-bold">{{ $recents->count() }} personne(s) ont déjà reçu un message il y a moins de {{ \App\Services\Admin\RelancesCiblees::DELAI_HEURES }} h.</p>
                        <p class="mt-1 text-sm text-gris-texte">{{ $recents->take(8)->map(fn ($i) => $i->participant->user->name)->join(', ') }}{{ $recents->count() > 8 ? '…' : '' }}. Elles ne le recevront pas, sauf si tu coches la case ci-dessous.</p>
                    </div>
                @endif
            @endif

            @if ($destinataires->isNotEmpty() || $etat['quand'] !== 'maintenant')
                <form method="POST" action="{{ route('admin.relances.store') }}" class="mt-6 space-y-4" data-envoi-unique>
                    @csrf
                    @foreach (array_merge($etat, ['programmee' => $programmee?->id_programmee]) as $nom => $valeur)
                        @continue($nom === 'chercher' || $valeur === null || $valeur === '')
                        <input type="hidden" name="{{ $nom }}" value="{{ $valeur }}">
                    @endforeach
                    @if ($recents->isNotEmpty())
                        <label class="flex items-start gap-3 text-base"><input type="checkbox" name="inclure_recents" value="1" class="mt-1 h-5 w-5"> L'envoyer aussi aux personnes qui viennent de recevoir un message</label>
                    @endif
                    <label class="flex items-start gap-3 text-base"><input type="checkbox" name="confirmation" value="1" class="mt-1 h-5 w-5"> J'ai relu le message, c'est bon.</label>
                    @error('confirmation')<p class="rfl-erreur">{{ $message }}</p>@enderror
                    <button type="submit" class="rfl-btn px-8 py-4 text-lg">{{ $etat['quand'] === 'maintenant' ? 'Envoyer maintenant' : ($programmee ? 'Enregistrer les changements' : 'Programmer l\'envoi') }}</button>
                </form>
            @endif
        </section>
        <script>document.getElementById('verification').scrollIntoView({ behavior: 'smooth' });</script>
    @endif

    <x-slot:scripts>
        <script>
            // Boutons « + Prénom »… : ajoutent la variable à l'endroit du curseur dans le message.
            document.querySelectorAll('[data-inserer-dans]').forEach((barre) => {
                const champ = document.getElementById(barre.dataset.insererDans);
                barre.addEventListener('click', (e) => {
                    const bouton = e.target.closest('[data-inserer]');
                    if (!bouton) return;
                    const debut = champ.selectionStart ?? champ.value.length;
                    const fin = champ.selectionEnd ?? debut;
                    champ.setRangeText(bouton.dataset.inserer, debut, fin, 'end');
                    champ.focus();
                });
            });
        </script>
    </x-slot:scripts>
</x-admin-shell>
