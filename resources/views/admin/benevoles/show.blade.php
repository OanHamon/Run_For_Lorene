@php
    use App\Models\CandidatureBenevole;
    use App\Support\Benevolat;

    $peutGerer = auth()->user()->peutAdmin(2);
    $nom = $c->user->prenom.' '.$c->user->nom;
@endphp
<x-admin-shell :title="$nom">
    <p class="mb-2 text-sm"><a href="{{ route('admin.benevoles.index') }}" class="rfl-lien">Bénévoles</a></p>
    <x-admin.titre :titre="$nom" :sous-titre="'Candidature n° '.$c->id_candidature.' · '.$c->evenement->nom">
        <span class="rfl-badge bg-fond px-3 py-1 text-sm text-vert-sombre">{{ $c->libelleStatut() }}</span>
    </x-admin.titre>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <div class="space-y-6">
            <section class="rfl-carte p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="font-display text-vert">Candidature</h2>
                        <p class="mt-1 text-sm text-gris-texte">{{ $c->user->email }}{{ $c->envoyee_le ? ' · envoyée le '.$c->envoyee_le->format('d/m/Y à H:i') : ' · pas encore envoyée' }}</p>
                    </div>
                    @if ($peutGerer && $c->photo)
                        <a href="{{ route('admin.benevoles.fichier', [$c, 'photo']) }}" target="_blank">
                            <img src="{{ route('admin.benevoles.fichier', [$c, 'photo']) }}" alt="Photo de badge de {{ $nom }}" class="h-28 w-28 rounded-2xl object-cover ring-1 ring-bordure">
                        </a>
                    @endif
                </div>
                @include('partials.candidature-benevole', ['c' => $c, 'sensible' => $peutGerer])
                @unless ($peutGerer)
                    <p class="mt-4 text-xs text-gris-texte">Contacts d'urgence, responsable légal, allergies et photo : réservés aux admins « Inscriptions et documents ».</p>
                @endunless
            </section>

            @if ($peutGerer)
                <section class="rfl-carte p-6">
                    <h2 class="font-display text-vert">Documents</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-gris-texte">Photo du badge</dt><dd>
                            @if ($c->photo)<a href="{{ route('admin.benevoles.fichier', [$c, 'photo']) }}" target="_blank" class="rfl-lien">Voir</a> <span class="text-gris-texte">(déposée le {{ $c->photo_le->format('d/m/Y') }})</span>@else<span class="text-gris-texte">Pas encore déposée</span>@endif
                        </dd></div>
                        @if ($c->autorisationRequise())
                            <div class="flex justify-between gap-4"><dt class="text-gris-texte">Autorisation parentale (moins de 16 ans)</dt><dd>
                                @if ($c->autorisation_parentale)<a href="{{ route('admin.benevoles.fichier', [$c, 'autorisation']) }}" target="_blank" class="rfl-lien">Voir</a> <span class="text-gris-texte">(déposée le {{ $c->autorisation_le->format('d/m/Y') }})</span>@else<span class="font-bold text-erreur">Manquante</span>@endif
                            </dd></div>
                        @endif
                    </dl>
                </section>
            @endif
        </div>

        <div class="space-y-6">
            @if ($peutGerer && $c->envoyee())
                <section class="rfl-carte p-6">
                    <h2 class="font-display text-vert">Décision</h2>
                    <form method="POST" action="{{ route('admin.benevoles.statut', $c) }}" class="mt-4 space-y-4">
                        @csrf
                        <x-admin.champ name="statut" label="Statut" sac="statut">
                            <select id="statut-statut" name="statut" class="rfl-champ-compact">
                                @foreach (CandidatureBenevole::STATUTS_ADMIN as $s)
                                    <option value="{{ $s }}" @selected(old('statut', $c->statut) === $s)>{{ CandidatureBenevole::LIBELLES[$s] }}</option>
                                @endforeach
                            </select>
                        </x-admin.champ>
                        <x-admin.champ name="mission_affectee" label="Mission affectée" sac="statut" aide="Visible par le bénévole une fois sa participation confirmée.">
                            <select id="statut-mission_affectee" name="mission_affectee" class="rfl-champ-compact">
                                <option value="">Pas encore affectée</option>
                                @foreach (Benevolat::MISSIONS as $code => $m)
                                    <option value="{{ $code }}" @selected(old('mission_affectee', $c->mission_affectee) === $code)>{{ $m['libelle'] }}{{ in_array($code, $c->missions_preferees ?? [], true) ? ' (souhait n° '.(array_search($code, $c->missions_preferees, true) + 1).')' : '' }}</option>
                                @endforeach
                            </select>
                        </x-admin.champ>
                        <x-admin.champ name="message" label="Message ajouté à l'email (facultatif)" sac="statut">
                            <textarea id="statut-message" name="message" rows="4" class="rfl-champ-compact" placeholder="Pour « Informations demandées », écris ici ta question.">{{ old('message') }}</textarea>
                        </x-admin.champ>
                        <label class="flex items-center gap-2 text-sm"><input type="hidden" name="notifier" value="0"><input type="checkbox" name="notifier" value="1" @checked(old('notifier', true))> Prévenir le bénévole par email</label>
                        <button type="submit" class="rfl-btn px-5 py-2.5 text-sm">Enregistrer</button>
                    </form>
                </section>
            @endif

            <section class="rfl-carte p-6">
                <h2 class="font-display text-vert">Historique</h2>
                <ol class="mt-3 space-y-3 text-sm">
                    @forelse ($c->historique as $h)
                        <li>
                            <p><strong>{{ CandidatureBenevole::LIBELLES[$h->statut] ?? $h->statut }}</strong> <span class="text-gris-texte">· {{ $h->created_at->format('d/m/Y H:i') }} · {{ $h->admin?->name ?? 'le bénévole' }}{{ $h->notifie ? ' · email envoyé' : '' }}</span></p>
                            @if ($h->message)<p class="mt-1 whitespace-pre-line text-gris-texte">{{ $h->message }}</p>@endif
                        </li>
                    @empty
                        <li class="text-gris-texte">Rien pour l'instant.</li>
                    @endforelse
                </ol>
            </section>
        </div>
    </div>
</x-admin-shell>
