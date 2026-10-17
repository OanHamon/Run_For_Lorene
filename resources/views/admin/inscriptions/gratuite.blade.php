@php
    $choix = old('participant');
    $ageJourJ = fn ($p) => $p->ageAu($evenement->date_evenement);
@endphp
<x-admin-shell title="Inscription gratuite">
    <p class="mb-2 text-sm"><a href="{{ route('admin.inscriptions.index') }}" class="rfl-lien">Inscriptions</a></p>
    <x-admin.titre titre="Inscription gratuite" :sous-titre="$evenement->nom.' · commande à 0 €, sans paiement'" />

    <div class="max-w-3xl space-y-6">
        {{-- 1. Le compte : on cherche d'abord par email --}}
        <form method="GET" action="{{ route('admin.inscriptions.create') }}" class="rfl-carte p-6">
            <h2 class="font-display text-vert">1. Le compte de la famille</h2>
            <p class="mt-1 text-sm text-gris-texte">La famille suivra l'inscription dans son espace : elle s'y connecte avec son email et un code reçu par email, sans mot de passe.</p>
            <div class="mt-4 flex flex-wrap items-end gap-3">
                <div class="min-w-64 flex-1">
                    <label for="email-recherche" class="rfl-label">Email du titulaire (parent ou participant majeur)</label>
                    <input id="email-recherche" name="email" type="email" value="{{ $email }}" required maxlength="254" autocomplete="off" class="rfl-champ-compact">
                </div>
                <button type="submit" class="rfl-btn px-5 py-2.5 text-sm">{{ $email ? 'Rechercher à nouveau' : 'Rechercher' }}</button>
            </div>
            @if ($email && ! $emailValide)
                <p class="rfl-erreur">Cette adresse email n'est pas valide.</p>
            @endif
        </form>

        @if ($emailValide && ! $compte)
            {{-- Une inscription ne crée jamais de compte : on le crée d'abord, à part. --}}
            <div class="rfl-alerte">
                <p class="font-display text-vert">Aucun compte avec {{ $email }}</p>
                <p class="mt-1 text-sm text-gris-texte">Vérifie d'abord l'orthographe avec la famille. Si l'adresse est bonne, crée son compte : la personne recevra un lien pour choisir son mot de passe, et tu reviendras ici pour l'inscription.</p>
                <a href="{{ route('admin.utilisateurs.create', ['email' => $email, 'retour' => 'inscription']) }}" class="mt-4 rfl-btn px-5 py-2.5 text-sm">Créer le compte</a>
            </div>
        @endif

        @if ($compte)
            <form method="POST" action="{{ route('admin.inscriptions.store') }}" class="group/form space-y-6">
                @csrf
                <input type="hidden" name="email" value="{{ $email }}">

                <section class="rfl-carte space-y-4 p-6">
                    <h2 class="font-display text-vert">2. Qui participe ?</h2>

                    <p class="rfl-alerte-info text-sm">
                        Compte trouvé : <strong>{{ $compte->name }}</strong> ({{ $compte->email }})
                        @if ($compte->mot_de_passe === null)· pas encore activé (mot de passe à créer) @endif.
                        Vérifie que c'est bien la bonne famille.
                    </p>

                    <fieldset>
                        <legend class="rfl-label">Participant</legend>
                        <div class="space-y-2 text-sm">
                            {{-- Le titulaire lui-même --}}
                            @php($titulaireInscrit = $compte && $dejaInscrits->has($compte->id_user))
                            <label class="flex items-start gap-3 rounded-2xl border border-bordure px-4 py-3 {{ $titulaireInscrit ? 'opacity-60' : 'hover:bg-fond' }}">
                                <input type="radio" name="participant" value="titulaire" class="mt-1" @checked($choix === 'titulaire') @disabled($titulaireInscrit) required>
                                <span>
                                    <strong>{{ $compte ? $compte->name : 'Le titulaire' }}</strong> (le titulaire lui-même)
                                    @if ($compte?->participant)
                                        <span class="block text-gris-texte">né(e) le {{ $compte->participant->date_naissance->format('d/m/Y') }} · {{ $ageJourJ($compte->participant) }} ans le jour J</span>
                                    @endif
                                    @if ($titulaireInscrit)<span class="block font-bold">Déjà inscrit(e) : {{ $dejaInscrits[$compte->id_user]->libelleStatut() }}</span>@endif
                                </span>
                            </label>

                            {{-- Les participants déjà rattachés au compte --}}
                            @foreach ($compte?->participantsGeres ?? [] as $p)
                                @php($inscrit = $dejaInscrits->has($p->id_user))
                                <label class="flex items-start gap-3 rounded-2xl border border-bordure px-4 py-3 {{ $inscrit ? 'opacity-60' : 'hover:bg-fond' }}">
                                    <input type="radio" name="participant" value="{{ $p->id_user }}" class="mt-1" @checked($choix === (string) $p->id_user) @disabled($inscrit)>
                                    <span>
                                        <strong>{{ $p->user->name }}</strong>
                                        <span class="block text-gris-texte">né(e) le {{ $p->date_naissance->format('d/m/Y') }} · {{ $ageJourJ($p) }} ans le jour J</span>
                                        @if ($inscrit)<span class="block font-bold">Déjà inscrit(e) : {{ $dejaInscrits[$p->id_user]->libelleStatut() }}</span>@endif
                                    </span>
                                </label>
                            @endforeach

                            <label class="flex items-start gap-3 rounded-2xl border border-bordure px-4 py-3 hover:bg-fond">
                                <input id="choix-nouveau" type="radio" name="participant" value="nouveau" class="mt-1" @checked($choix === 'nouveau')>
                                <span><strong>Une nouvelle personne</strong> (un enfant, un proche…) rattachée à ce compte</span>
                            </label>
                        </div>
                        @error('participant')<p class="rfl-erreur">{{ $message }}</p>@enderror
                    </fieldset>

                    {{-- Champs utiles selon le choix : nouvelle personne, ou titulaire sans date de naissance connue --}}
                    <div class="hidden grid-cols-1 gap-4 rounded-2xl bg-fond p-4 group-has-[#choix-nouveau:checked]/form:grid sm:grid-cols-3 {{ $errors->hasAny(['prenom', 'nom']) ? '!grid' : '' }}">
                        <x-admin.champ name="prenom" label="Prénom" maxlength="100" />
                        <x-admin.champ name="nom" label="Nom" :value="$compte?->nom" maxlength="100" />
                        <x-admin.champ name="date_naissance" label="Date de naissance" type="date" id="date-nouveau" />
                    </div>
                    @if (! $compte?->participant)
                        <div class="hidden rounded-2xl bg-fond p-4 group-has-[input[value=titulaire]:checked]/form:block">
                            <x-admin.champ name="date_naissance_titulaire" label="Date de naissance du titulaire" type="date" aide="Nécessaire pour vérifier l'âge le jour de l'événement." />
                        </div>
                    @endif
                </section>

                <section class="rfl-carte space-y-4 p-6">
                    <h2 class="font-display text-vert">3. Course et formalités</h2>
                    <x-admin.champ name="id_course" label="Course">
                        <select id="id_course" name="id_course" class="rfl-champ-compact" required>
                            <option value="">Choisir…</option>
                            @foreach ($courses as $c)
                                <option value="{{ $c->id_course }}" @selected((int) old('id_course') === $c->id_course)>{{ $c->nom }}{{ $c->estComplete() ? ' (complète)' : '' }}</option>
                            @endforeach
                        </select>
                    </x-admin.champ>
                    <x-admin.champ name="motif" label="Motif (facultatif)" maxlength="255" aide="Partenaire, bénévole, cas particulier… Noté dans le journal." />

                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" name="consentements" value="1" class="mt-1" @checked(old('consentements')) required>
                        <span>Le participant (ou son responsable) a accepté le <a href="{{ route('textes.show', 'reglement') }}" target="_blank" rel="noopener" class="rfl-lien">règlement</a> et la <a href="{{ route('textes.show', 'rgpd') }}" target="_blank" rel="noopener" class="rfl-lien">politique de protection des données</a>. Ces consentements sont enregistrés à ton nom.</span>
                    </label>
                    @error('consentements')<p class="rfl-erreur">{{ $message }}</p>@enderror
                    <input type="hidden" name="prevenir" value="0">
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" name="prevenir" value="1" class="mt-1" @checked(old('prevenir', true))>
                        <span>Prévenir la famille par email ({{ $email }}) : un lien vers l'inscription pour remplir la décharge, et le questionnaire de santé pour un mineur{{ $compte->mot_de_passe === null ? ', après avoir créé son mot de passe' : '' }}.</span>
                    </label>
                </section>

                <div class="flex justify-end">
                    <button type="submit" class="rfl-btn px-6 py-3">Créer l'inscription gratuite</button>
                </div>
            </form>
        @endif
    </div>
</x-admin-shell>
