@php
    use App\Models\Admin;
    use App\Support\Telephone;

    $u = $utilisateur;
    $moi = $u->is(auth()->user());
@endphp
<x-admin-shell :title="$u->name">
    <p class="mb-2 text-sm"><a href="{{ route('admin.utilisateurs.index') }}" class="rfl-lien">Utilisateurs</a></p>
    <x-admin.titre :titre="$u->name" :sous-titre="$u->email.($u->hasVerifiedEmail() ? '' : ' · email non vérifié').' · compte créé le '.$u->created_at?->format('d/m/Y')" />

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <section class="rfl-carte p-6">
                <h2 class="mb-3 font-display text-vert">Compte</h2>
                <dl class="divide-y divide-bordure text-sm">
                    <div class="flex justify-between gap-4 py-1.5"><dt class="text-gris-texte">Téléphone</dt><dd class="font-bold">{{ Telephone::lisible($u->telephone) ?: '–' }}</dd></div>
                    <div class="flex justify-between gap-4 py-1.5"><dt class="text-gris-texte">Connexion</dt><dd class="font-bold">{{ $u->mot_de_passe ? 'mot de passe (ou code si oublié)' : 'pas encore activé : mot de passe à créer' }}</dd></div>
                    @if ($u->participant)
                        <div class="flex justify-between gap-4 py-1.5"><dt class="text-gris-texte">Date de naissance</dt><dd class="font-bold">{{ $u->participant->date_naissance->format('d/m/Y') }}</dd></div>
                        <div class="flex justify-between gap-4 py-1.5"><dt class="text-gris-texte">Ville</dt><dd class="font-bold">{{ trim("{$u->participant->code_postal} {$u->participant->ville}") ?: '–' }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="rfl-carte overflow-hidden">
                <h2 class="px-6 py-4 font-display text-vert">Participants gérés</h2>
                <table class="rfl-tableau">
                    <tbody class="border-t border-bordure">
                        @forelse ($u->participantsGeres as $p)
                            <tr>
                                <td class="font-bold">{{ $p->user->name }}</td>
                                <td class="text-gris-texte">né(e) le {{ $p->date_naissance->format('d/m/Y') }}</td>
                                <td class="text-gris-texte">{{ $p->email_contact }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-gris-texte">Aucun.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <section class="rfl-carte overflow-hidden">
                <h2 class="px-6 py-4 font-display text-vert">Inscriptions</h2>
                <table class="rfl-tableau">
                    <tbody class="border-t border-bordure">
                        @forelse ($inscriptions as $i)
                            <tr>
                                <td><a href="{{ route('admin.inscriptions.show', $i) }}" class="rfl-lien">{{ $i->participant->user->name }}</a></td>
                                <td>{{ $i->tarif->course->nom }} <span class="text-gris-texte">· {{ $i->evenement->nom }}</span></td>
                                <td><x-admin.statut :inscription="$i" /></td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-gris-texte">Aucune inscription.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="rfl-carte p-6">
                <h2 class="mb-1 font-display text-vert">Accès admin</h2>
                <p class="mb-4 text-sm text-gris-texte">Actuellement : {{ Admin::LIBELLES[$u->niveauAdmin()] ?? 'aucun (participant)' }}.</p>
                @if ($moi)
                    <p class="text-sm text-gris-texte">Ton propre niveau se change par un autre super admin.</p>
                @else
                    @php
                        $actuel = $u->niveauAdmin();
                        $choisi = (int) old('niveau_acces', $actuel);
                    @endphp
                    {{-- Donner plus de droits demande une confirmation (vérifiée côté serveur) ; super admin : recopier l'email. --}}
                    <form method="POST" action="{{ route('admin.utilisateurs.niveau', $u) }}" class="group/niveau space-y-3"
                        onsubmit="const n = +this.niveau_acces.value; return n <= {{ $actuel }} || confirm(n === 3 ? 'Donner TOUS les droits (super admin) à {{ addslashes($u->name) }} ?' : 'Donner cet accès admin à {{ addslashes($u->name) }} ?')">
                        @csrf
                        @method('PUT')
                        <fieldset class="space-y-2 text-sm">
                            <legend class="sr-only">Niveau d'accès</legend>
                            @foreach ([0 => 'Aucun (participant)'] + Admin::LIBELLES as $niveau => $libelle)
                                <label class="flex items-start gap-2">
                                    <input type="radio" name="niveau_acces" value="{{ $niveau }}" id="niveau-{{ $niveau }}" class="mt-1" @checked($choisi === $niveau)>
                                    <span>
                                        {{ $libelle }}
                                        <span class="block text-xs text-gris-texte">{{ [0 => 'Pas d\'accès au back-office', 1 => 'Consulte tout, sauf les réponses de santé', 2 => 'Gère inscriptions, dossards et documents', 3 => 'Tous les droits : paramétrage, remboursements, admins'][$niveau] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </fieldset>

                        @php
                            $promotions = collect([1, 2, 3])->filter(fn ($n) => $n > $actuel);
                            // Classes écrites en entier pour que Tailwind les génère : le bloc apparaît dès qu'un niveau supérieur est coché.
                            $siCoche = [1 => 'group-has-[#niveau-1:checked]/niveau:block', 2 => 'group-has-[#niveau-2:checked]/niveau:block', 3 => 'group-has-[#niveau-3:checked]/niveau:block'];
                        @endphp
                        @if ($promotions->isNotEmpty())
                            <div class="{{ $errors->niveau->any() ? '' : 'hidden' }} {{ $promotions->map(fn ($n) => $siCoche[$n])->join(' ') }} space-y-3 rounded-2xl border border-moutarde/40 bg-moutarde/10 p-4 text-sm">
                                <p class="font-bold">Tu donnes plus de droits à {{ $u->prenom }}.</p>
                                <label class="flex items-start gap-2">
                                    <input type="checkbox" name="confirmation" value="1" class="mt-1">
                                    <span>Je confirme : {{ $u->name }} ({{ $u->email }}) aura cet accès à l'administration. Il ou elle en sera informé(e) par email.</span>
                                </label>
                                @error('confirmation', 'niveau')<p class="rfl-erreur">{{ $message }}</p>@enderror
                                @if ($actuel < 3)
                                    <div class="hidden group-has-[#niveau-3:checked]/niveau:block {{ $errors->niveau->has('confirmation_email') ? '!block' : '' }}">
                                        <label for="confirmation_email" class="rfl-label">Super admin : recopie son email pour confirmer</label>
                                        <input id="confirmation_email" name="confirmation_email" type="email" autocomplete="off" class="rfl-champ-compact" placeholder="{{ $u->email }}">
                                        @error('confirmation_email', 'niveau')<p class="rfl-erreur">{{ $message }}</p>@enderror
                                    </div>
                                @endif
                            </div>
                        @endif

                        <button type="submit" class="rfl-btn w-full px-5 py-2.5 text-sm">Enregistrer</button>
                    </form>
                @endif
            </section>

            @if ($u->mot_de_passe === null)
                <section class="rfl-carte p-6">
                    <h2 class="mb-1 font-display text-vert">Compte pas encore activé</h2>
                    <p class="mb-4 text-sm text-gris-texte">La personne n'a pas encore choisi son mot de passe. Le lien reçu par email est valable {{ \App\Support\Activation::VALIDITE_JOURS }} jours ; tu peux en renvoyer un nouveau.</p>
                    <form method="POST" action="{{ route('admin.utilisateurs.activation', $u) }}">
                        @csrf
                        <button type="submit" class="rfl-btn w-full px-5 py-2.5 text-sm">Renvoyer le lien</button>
                    </form>
                </section>
            @endif

            @unless ($u->hasVerifiedEmail())
                <section class="rfl-carte p-6">
                    <h2 class="mb-1 font-display text-vert">Corriger l'email</h2>
                    <p class="mb-4 text-sm text-gris-texte">Ce compte ne s'est encore jamais connecté : si l'adresse a été mal saisie, corrige-la, puis relance la famille depuis la fiche de l'inscription.</p>
                    <form method="POST" action="{{ route('admin.utilisateurs.email', $u) }}" class="space-y-3">
                        @csrf
                        @method('PUT')
                        <x-admin.champ name="email" label="Adresse email" type="email" :value="$u->email" sac="email" required maxlength="254" />
                        <button type="submit" class="rfl-btn w-full px-5 py-2.5 text-sm">Enregistrer l'email</button>
                    </form>
                </section>
            @endunless

            @unless ($moi)
                <section class="rfl-carte p-6">
                    <h2 class="mb-1 font-display text-vert">Réinitialiser l'accès</h2>
                    <p class="mb-4 text-sm text-gris-texte">Efface le mot de passe et ferme les sessions ouvertes. La personne reçoit un lien pour en choisir un nouveau.</p>
                    <form method="POST" action="{{ route('admin.utilisateurs.reinitialiser', $u) }}" onsubmit="return confirm('Réinitialiser l\'accès de ce compte ?')">
                        @csrf
                        <button type="submit" class="rfl-btn-secondaire w-full px-5 py-2.5 text-sm">Réinitialiser</button>
                    </form>
                </section>
            @endunless

            @unless ($moi)
                <section class="rounded-3xl border border-rose-200/70 bg-white p-6">
                    <h2 class="mb-1 font-display text-vert">Supprimer le compte</h2>
                    @if ($obstaclesSuppression)
                        <p class="text-sm text-gris-texte">Impossible pour ce compte :</p>
                        <ul class="mt-1 list-disc pl-5 text-sm text-gris-texte">
                            @foreach ($obstaclesSuppression as $obstacle)
                                <li>{{ ucfirst($obstacle) }}.</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mb-4 text-sm text-gris-texte">Ce compte n'a jamais servi : il part définitivement, avec ses participants gérés{{ $u->participantsGeres->isNotEmpty() ? ' ('.$u->participantsGeres->map(fn ($p) => $p->user->prenom)->join(', ').')' : '' }} et ses inscriptions non terminées.</p>
                        <form method="POST" action="{{ route('admin.utilisateurs.destroy', $u) }}" onsubmit="return confirm('Supprimer définitivement le compte de {{ addslashes($u->name) }} ?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rfl-btn-secondaire w-full border-rose-300 px-5 py-2.5 text-sm text-rose-800 hover:border-rose-500">Supprimer le compte</button>
                        </form>
                    @endif
                </section>
            @endunless
        </aside>
    </div>
</x-admin-shell>
