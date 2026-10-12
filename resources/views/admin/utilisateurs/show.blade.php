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
                    <div class="flex justify-between gap-4 py-1.5"><dt class="text-gris-texte">Connexion</dt><dd class="font-bold">{{ $u->mot_de_passe ? 'mot de passe ou code email' : 'code email uniquement' }}</dd></div>
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
                    <form method="POST" action="{{ route('admin.utilisateurs.niveau', $u) }}" class="space-y-3">
                        @csrf
                        @method('PUT')
                        <fieldset class="space-y-2 text-sm">
                            <legend class="sr-only">Niveau d'accès</legend>
                            @foreach ([0 => 'Aucun (participant)'] + Admin::LIBELLES as $niveau => $libelle)
                                <label class="flex items-start gap-2">
                                    <input type="radio" name="niveau_acces" value="{{ $niveau }}" class="mt-1" @checked($u->niveauAdmin() === $niveau)>
                                    <span>
                                        {{ $libelle }}
                                        <span class="block text-xs text-gris-texte">{{ [0 => 'Pas d\'accès au back-office', 1 => 'Consulte tout, sauf les réponses de santé', 2 => 'Gère inscriptions, dossards et documents', 3 => 'Paramétrage, remboursements et admins'][$niveau] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </fieldset>
                        <button type="submit" class="rfl-btn w-full px-5 py-2.5 text-sm">Enregistrer</button>
                    </form>
                @endif
            </section>

            @unless ($moi)
                <section class="rfl-carte p-6">
                    <h2 class="mb-1 font-display text-vert">Réinitialiser l'accès</h2>
                    <p class="mb-4 text-sm text-gris-texte">Efface le mot de passe et ferme les sessions ouvertes. La personne se reconnecte avec un code reçu par email.</p>
                    <form method="POST" action="{{ route('admin.utilisateurs.reinitialiser', $u) }}" onsubmit="return confirm('Réinitialiser l\'accès de ce compte ?')">
                        @csrf
                        <button type="submit" class="rfl-btn-secondaire w-full px-5 py-2.5 text-sm">Réinitialiser</button>
                    </form>
                </section>
            @endunless
        </aside>
    </div>
</x-admin-shell>
