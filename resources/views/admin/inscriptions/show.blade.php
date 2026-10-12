@php
    use App\Models\Commande;
    use App\Models\Inscription;
    use App\Http\Controllers\TexteLegalController;
    use App\Services\Admin\AnnulerInscription;
    use App\Support\Format;
    use App\Support\Telephone;

    $p = $i->participant;
    $course = $i->tarif->course;
    $peutGerer = auth()->user()->peutAdmin(2);
    $manquants = in_array($i->statut, [Inscription::ANNULEE, Inscription::BROUILLON], true) ? [] : $i->documentsManquants();
    $relancable = in_array($i->statut, [Inscription::EN_ATTENTE_PAIEMENT, Inscription::DOSSIER_INCOMPLET], true);
    $ligne = fn ($libelle, $valeur) => $valeur === null || $valeur === '' ? '' : '<div class="flex justify-between gap-4 py-1.5"><dt class="text-gris-texte">'.e($libelle).'</dt><dd class="text-right font-bold">'.e($valeur).'</dd></div>';
@endphp
<x-admin-shell :title="$p->user->name">
    <p class="mb-2 text-sm"><a href="{{ route('admin.inscriptions.index') }}" class="rfl-lien">Inscriptions</a></p>
    <x-admin.titre :titre="$p->user->name" :sous-titre="'Inscription n° '.$i->id_inscription.' · '.$course->nom.' · '.$i->evenement->nom">
        <x-admin.statut :inscription="$i" class="px-3 py-1 text-sm" />
        @if ($peutGerer && $relancable)
            <form method="POST" action="{{ route('admin.inscriptions.relancer', $i) }}">
                @csrf
                <button type="submit" class="rfl-btn-secondaire px-4 py-2 text-sm">Relancer par email</button>
            </form>
        @endif
        @if ($peutGerer && in_array($i->statut, AnnulerInscription::ANNULABLES, true))
            <form method="POST" action="{{ route('admin.inscriptions.annuler', $i) }}" onsubmit="return confirm('Annuler cette inscription ? Le dossard sera libéré.')">
                @csrf
                <button type="submit" class="rfl-btn-secondaire px-4 py-2 text-sm">Annuler l'inscription</button>
            </form>
        @endif
    </x-admin.titre>

    @if ($manquants)
        <div class="mb-6 rfl-alerte">
            <p class="font-display text-vert">Dossier incomplet</p>
            <p class="mt-1 text-sm text-gris-texte">Il manque {{ implode(' et ', $manquants) }}. Le participant le remplit depuis son espace ; le dossard est attribué ensuite.</p>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-2">
        {{-- Participant --}}
        <section class="rfl-carte p-6">
            <h2 class="mb-3 font-display text-vert">Participant</h2>
            <dl class="divide-y divide-bordure text-sm">
                {!! $ligne('Date de naissance', $p->date_naissance->format('d/m/Y').' ('.$p->ageAu($i->evenement->date_evenement).' ans le jour J)') !!}
                {!! $ligne('Sexe', $p->sexe) !!}
                {!! $ligne('Email', $p->user->email ?? $p->email_contact) !!}
                {!! $ligne('Téléphone', Telephone::lisible($p->user->telephone)) !!}
                {!! $ligne('Adresse', trim(collect([$p->adresse, trim("{$p->code_postal} {$p->ville}"), $p->pays !== 'France' ? $p->pays : null])->filter()->join(', '))) !!}
                {!! $ligne('Contact d\'urgence', trim(($i->urgence_nom ?? '').' '.Telephone::lisible($i->urgence_tel))) !!}
                @if ($p->responsable)
                    {!! $ligne('Géré par', $p->responsable->name.' ('.$p->responsable->email.')') !!}
                @endif
                @if ($titulaire && $titulaire->id_user !== $p->id_user && $titulaire->id_user !== $p->responsable?->id_user)
                    {!! $ligne('Inscrit par', $titulaire->name.' ('.$titulaire->email.')') !!}
                @endif
            </dl>
        </section>

        {{-- Course et dossard --}}
        <section class="rfl-carte p-6">
            <h2 class="mb-3 font-display text-vert">Course et dossard</h2>
            <dl class="divide-y divide-bordure text-sm">
                {!! $ligne('Course', $course->nom.' · départ '.substr($course->heure_depart, 0, 5)) !!}
                {!! $ligne('Tarif', $i->tarif->libelle.' · '.Format::euros($i->prix_paye).' payé') !!}
                @foreach (config('rfl.options') ? $i->declinaisons : [] as $d)
                    {!! $ligne($d->declinaison->optionEvenement->libelle, $d->declinaison->libelle.' · '.((float) $d->prix_unitaire > 0 ? Format::euros($d->prix_unitaire) : 'offert')) !!}
                @endforeach
                {!! $ligne('Plage de la course', $course->dossard_debut !== null ? "{$course->dossard_debut}–{$course->dossard_fin}" : 'non définie') !!}
            </dl>

            <div class="mt-5 rounded-2xl bg-vert p-5 text-white">
                <p class="text-sm text-vert-clair">Dossard</p>
                <p class="font-display text-4xl text-moutarde">{{ $i->dossard ?? '–' }}</p>
                @if ($peutGerer && $i->statut === Inscription::VALIDEE)
                    <form method="POST" action="{{ route('admin.inscriptions.dossard', $i) }}" class="mt-4 flex flex-wrap items-end gap-3">
                        @csrf
                        @method('PUT')
                        <div>
                            <label for="dossard" class="mb-1 block text-sm text-vert-clair">Modifier le numéro</label>
                            <input id="dossard" name="dossard" type="number" min="1" value="{{ old('dossard', $i->dossard) }}" required class="h-11 w-32 rounded-2xl border-0 bg-white px-3 text-vert-sombre focus:ring-2 focus:ring-moutarde">
                        </div>
                        <button type="submit" class="rounded-full bg-white px-5 py-2.5 font-display text-sm text-vert hover:bg-vert-clair">Enregistrer</button>
                    </form>
                    @error('dossard')<p class="mt-2 rounded-xl bg-white px-3 py-2 text-sm text-rose-800">{{ $message }}</p>@enderror
                    @if ($i->dossard === null)
                        <form method="POST" action="{{ route('admin.inscriptions.attribuer-dossard', $i) }}" class="mt-3">
                            @csrf
                            <button type="submit" class="text-sm text-white underline underline-offset-4">Attribuer le premier numéro libre</button>
                        </form>
                    @endif
                @elseif ($i->statut !== Inscription::VALIDEE)
                    <p class="mt-1 text-sm text-vert-clair">Attribué à la validation de l'inscription.</p>
                @endif
            </div>
        </section>

        {{-- Paiement --}}
        <section class="rfl-carte p-6">
            <h2 class="mb-3 font-display text-vert">Commande et paiement</h2>
            @if (! $i->commande)
                <p class="text-sm text-gris-texte">Pas encore de commande (brouillon).</p>
            @else
                <dl class="divide-y divide-bordure text-sm">
                    {!! $ligne('Commande', 'n° '.$i->commande->id_commande.' du '.$i->commande->date_commande->format('d/m/Y H:i')) !!}
                    {!! $ligne('Passée par', $i->commande->user->name) !!}
                    {!! $ligne('Montant total', $i->commande->statut === Commande::GRATUITE ? 'gratuite (inscription admin)' : Format::euros($i->commande->montant_total)) !!}
                    @if ($i->commande->don)
                        {!! $ligne('Dont don', Format::euros($i->commande->don->montant)) !!}
                    @endif
                    @foreach ($i->commande->paiements as $paiement)
                        {!! $ligne('Paiement du '.$paiement->created_at->format('d/m/Y H:i'), ['en_attente' => 'en attente', 'reussi' => 'réussi', 'echoue' => 'échoué'][$paiement->statut].' · '.Format::euros($paiement->montant)) !!}
                        @foreach ($paiement->remboursements as $r)
                            {!! $ligne('Remboursé le '.$r->date_remboursement->format('d/m/Y'), Format::euros($r->montant)) !!}
                        @endforeach
                    @endforeach
                </dl>
                @if (Route::has('admin.commandes.show'))
                    <a href="{{ route('admin.commandes.show', $i->commande) }}" class="mt-4 inline-block rfl-lien text-sm">Voir la commande{{ auth()->user()->peutAdmin(3) ? ' et rembourser' : '' }}</a>
                @endif
            @endif
        </section>

        {{-- Documents --}}
        <section class="rfl-carte p-6">
            <h2 class="mb-3 font-display text-vert">Documents</h2>
            <dl class="divide-y divide-bordure text-sm">
                <div class="flex justify-between gap-4 py-1.5">
                    <dt class="text-gris-texte">Décharge de responsabilité</dt>
                    @php($acceptee = $historique->first(fn ($c) => $c->type_texte === 'decharge' && $c->accepte))
                    <dd class="text-right font-bold">{{ $i->dechargeManquante() || ! $acceptee ? 'à remplir' : 'remplie le '.\Illuminate\Support\Carbon::parse($acceptee->date_depot)->format('d/m/Y') }}</dd>
                </div>
                <div class="flex justify-between gap-4 py-1.5">
                    <dt class="text-gris-texte">Questionnaire de santé</dt>
                    <dd class="text-right font-bold">
                        @if (! $i->santeRequise())
                            non demandé (majeur)
                        @elseif (! $sante)
                            à remplir
                        @else
                            rempli le {{ $sante->document->date_depot->format('d/m/Y') }}
                            @if ($sante->document->date_validation)
                                <span class="ml-1 rfl-badge bg-vert-clair text-vert">vérifié</span>
                            @endif
                            @if ($peutGerer && Route::has('admin.questionnaires.show'))
                                <a href="{{ route('admin.questionnaires.show', $sante->id_document) }}" class="ml-2 rfl-lien font-normal">Consulter</a>
                            @endif
                        @endif
                    </dd>
                </div>
            </dl>

            <h3 class="mt-5 mb-2 text-sm font-bold">Consentements</h3>
            @if ($historique->isEmpty())
                <p class="text-sm text-gris-texte">Aucun consentement enregistré.</p>
            @else
                <ul class="space-y-1 text-sm">
                    @foreach ($historique as $c)
                        <li class="flex flex-wrap justify-between gap-x-4">
                            <span>{{ TexteLegalController::LIBELLES[$c->type_texte] ?? $c->type_texte }} v{{ $c->version }} : <strong>{{ $c->accepte ? 'accepté' : 'refusé' }}</strong></span>
                            <span class="text-gris-texte">{{ \Illuminate\Support\Carbon::parse($c->date_depot)->format('d/m/Y H:i') }} · IP {{ $c->ip }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    @if ($journal->isNotEmpty())
        <section class="mt-6 rfl-carte p-6">
            <h2 class="mb-3 font-display text-vert">Actions admin</h2>
            <ul class="space-y-1 text-sm">
                @foreach ($journal as $ligneJournal)
                    <li>
                        <span class="text-gris-texte">{{ $ligneJournal->created_at->format('d/m/Y H:i') }}</span>
                        · <strong>{{ $ligneJournal->libelle() }}</strong> par {{ $ligneJournal->auteur->name }}
                        @if ($ligneJournal->details)
                            <span class="text-gris-texte">({{ collect($ligneJournal->details)->map(fn ($v, $k) => "{$k} : ".($v ?? '–'))->join(', ') }})</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-admin-shell>
