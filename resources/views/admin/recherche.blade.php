@php use App\Models\Admin; @endphp
<x-admin-shell title="Recherche">
    <x-admin.titre titre="Recherche" :sous-titre="$terme !== '' ? '« '.$terme.' »'.($evenement ? ' · inscriptions de '.$evenement->nom : '') : 'Nom, email, numéro de dossard ou de commande'" />

    <form method="GET" class="rfl-carte mb-6 flex flex-wrap items-end gap-4 p-5" role="search">
        <div class="min-w-64 flex-1">
            <label for="q" class="rfl-label">Rechercher</label>
            <input id="q" name="q" type="search" value="{{ $terme }}" autofocus placeholder="Camille Martin, camille@exemple.fr, 1042…" class="rfl-champ-compact">
        </div>
        <button type="submit" class="rfl-btn px-5 py-2.5 text-sm">Rechercher</button>
    </form>

    @if ($terme !== '' && $inscriptions->isEmpty() && $comptes->isEmpty() && $commandes->isEmpty())
        <div class="rfl-carte px-6 py-10 text-center text-gris-texte">Aucun résultat. Vérifie l'orthographe, ou cherche par email.</div>
    @endif

    @if ($inscriptions->isNotEmpty())
        <section class="rfl-carte mb-6 overflow-hidden">
            <h2 class="px-6 py-4 font-display text-vert">Inscriptions</h2>
            <div class="overflow-x-auto">
                <table class="rfl-tableau min-w-[560px]">
                    <thead><tr><th>Participant</th><th>Course</th><th>Statut</th><th class="text-right">Dossard</th></tr></thead>
                    <tbody>
                        @foreach ($inscriptions as $i)
                            <tr class="relative hover:bg-fond/60">
                                <td class="font-bold"><a href="{{ route('admin.inscriptions.show', $i) }}" class="after:absolute after:inset-0">{{ $i->participant->user->name }}</a></td>
                                <td>{{ $i->tarif->course->nom }}</td>
                                <td><x-admin.statut :inscription="$i" /></td>
                                <td class="text-right">{{ $i->dossard ?? '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($commandes->isNotEmpty())
        <section class="rfl-carte mb-6 overflow-hidden">
            <h2 class="px-6 py-4 font-display text-vert">Commandes</h2>
            <ul class="divide-y divide-bordure border-t border-bordure">
                @foreach ($commandes as $c)
                    <li class="relative px-6 py-3 hover:bg-fond/60">
                        <a href="{{ route('admin.commandes.show', $c) }}" class="font-bold after:absolute after:inset-0">Commande n° {{ $c->id_commande }}</a>
                        <span class="text-sm text-gris-texte">· {{ $c->user?->name }} · {{ $c->date_commande?->format('d/m/Y') }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($comptes->isNotEmpty())
        <section class="rfl-carte overflow-hidden">
            <h2 class="px-6 py-4 font-display text-vert">Comptes</h2>
            <ul class="divide-y divide-bordure border-t border-bordure">
                @foreach ($comptes as $u)
                    <li class="relative flex items-center justify-between gap-3 px-6 py-3 hover:bg-fond/60">
                        <span><a href="{{ route('admin.utilisateurs.show', $u) }}" class="font-bold after:absolute after:inset-0">{{ $u->name }}</a> <span class="text-sm text-gris-texte">· {{ $u->email }}</span></span>
                        @if ($u->admin)<span class="rfl-badge bg-vert-clair text-vert">{{ Admin::LIBELLES[$u->admin->niveau_acces] }}</span>@endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-admin-shell>
