<x-admin-shell title="Inscription gratuite">
    <p class="mb-2 text-sm"><a href="{{ route('admin.inscriptions.index') }}" class="rfl-lien">Inscriptions</a></p>
    <x-admin.titre titre="Inscription gratuite" :sous-titre="$evenement->nom.' · commande à 0 €, sans paiement'" />

    <form method="POST" action="{{ route('admin.inscriptions.store') }}" class="max-w-3xl space-y-6">
        @csrf

        <section class="rfl-carte space-y-4 p-6">
            <h2 class="font-display text-vert">Compte</h2>
            <p class="text-sm text-gris-texte">Le titulaire du compte suit l'inscription dans son espace et y remplit la décharge (et le questionnaire de santé d'un mineur). S'il n'a pas de compte, il est créé : il se connectera avec un code reçu par email.</p>
            <x-admin.champ name="email" label="Email du titulaire" type="email" required maxlength="254" autocomplete="off" />
            <fieldset>
                <legend class="rfl-label">Qui participe ?</legend>
                <div class="flex flex-wrap gap-4 text-sm">
                    <label class="flex items-center gap-2"><input type="radio" name="pour" value="titulaire" @checked(old('pour', 'titulaire') === 'titulaire')> Le titulaire lui-même</label>
                    <label class="flex items-center gap-2"><input type="radio" name="pour" value="gere" @checked(old('pour') === 'gere')> Une personne qu'il inscrit (enfant…)</label>
                </div>
            </fieldset>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.champ name="titulaire_prenom" label="Prénom du titulaire" maxlength="100" aide="Seulement pour créer le compte d'un titulaire qui inscrit quelqu'un d'autre." />
                <x-admin.champ name="titulaire_nom" label="Nom du titulaire" maxlength="100" />
            </div>
        </section>

        <section class="rfl-carte space-y-4 p-6">
            <h2 class="font-display text-vert">Participant et course</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-admin.champ name="prenom" label="Prénom du participant" required maxlength="100" />
                <x-admin.champ name="nom" label="Nom du participant" required maxlength="100" />
                <x-admin.champ name="date_naissance" label="Date de naissance" type="date" required aide="Ignorée si le participant existe déjà." />
                <x-admin.champ name="id_course" label="Course">
                    <select id="id_course" name="id_course" class="rfl-champ-compact" required>
                        <option value="">Choisir…</option>
                        @foreach ($courses as $c)
                            <option value="{{ $c->id_course }}" @selected((int) old('id_course') === $c->id_course)>{{ $c->nom }}{{ $c->estComplete() ? ' (complète)' : '' }}</option>
                        @endforeach
                    </select>
                </x-admin.champ>
            </div>
            <x-admin.champ name="motif" label="Motif (facultatif)" maxlength="255" aide="Partenaire, bénévole, cas particulier… Noté dans le journal." />
        </section>

        <section class="rfl-carte space-y-3 p-6 text-sm">
            <label class="flex items-start gap-3">
                <input type="checkbox" name="consentements" value="1" class="mt-1" @checked(old('consentements')) required>
                <span>Le participant (ou son responsable) a accepté le <a href="{{ route('textes.show', 'reglement') }}" target="_blank" rel="noopener" class="rfl-lien">règlement</a> et la <a href="{{ route('textes.show', 'rgpd') }}" target="_blank" rel="noopener" class="rfl-lien">politique de protection des données</a>. Ces consentements sont enregistrés à ton nom.</span>
            </label>
            @error('consentements')<p class="rfl-erreur">{{ $message }}</p>@enderror
            <input type="hidden" name="prevenir" value="0">
            <label class="flex items-start gap-3">
                <input type="checkbox" name="prevenir" value="1" class="mt-1" @checked(old('prevenir', true))>
                <span>Prévenir le titulaire par email (lien vers son espace pour compléter le dossier).</span>
            </label>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="rfl-btn px-6 py-3">Créer l'inscription gratuite</button>
        </div>
    </form>
</x-admin-shell>
