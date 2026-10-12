@php use App\Models\Admin; @endphp
<x-admin-shell title="Utilisateurs">
    <x-admin.titre titre="Utilisateurs" sous-titre="Comptes avec email. Les participants gérés (enfants…) apparaissent sur la fiche de leur responsable." />

    <form method="GET" class="rfl-carte mb-6 flex flex-wrap items-end gap-4 p-5" role="search">
        <div class="min-w-64 flex-1">
            <label for="q" class="rfl-label">Rechercher</label>
            <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="Nom, email, ou nom d'un participant géré" class="rfl-champ-compact">
        </div>
        <label class="flex items-center gap-2 pb-3 text-sm"><input type="checkbox" name="admins" value="1" @checked(request()->boolean('admins'))> Admins seulement</label>
        <button type="submit" class="rfl-btn px-5 py-2.5 text-sm">Filtrer</button>
    </form>

    <div class="rfl-carte overflow-hidden">
        <div class="overflow-x-auto">
            <table class="rfl-tableau min-w-[640px]">
                <thead><tr><th>Nom</th><th>Email</th><th class="text-right">Participants gérés</th><th>Accès</th><th>Créé le</th></tr></thead>
                <tbody>
                    @forelse ($utilisateurs as $u)
                        <tr class="relative hover:bg-fond/60">
                            <td class="font-bold"><a href="{{ route('admin.utilisateurs.show', $u) }}" class="after:absolute after:inset-0">{{ $u->name }}</a></td>
                            <td>{{ $u->email }}@unless ($u->email_verifie_le)<span class="ml-1 text-xs text-gris-texte">(non vérifié)</span>@endunless</td>
                            <td class="text-right">{{ $u->participants_geres_count ?: '–' }}</td>
                            <td>
                                @if ($u->admin)
                                    <span class="rfl-badge bg-vert-clair text-vert">{{ Admin::LIBELLES[$u->admin->niveau_acces] }}</span>
                                @else
                                    <span class="text-gris-texte">Participant</span>
                                @endif
                            </td>
                            <td class="text-gris-texte">{{ $u->created_at?->format('d/m/Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-gris-texte">Aucun compte.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">{{ $utilisateurs->links() }}</div>
</x-admin-shell>
