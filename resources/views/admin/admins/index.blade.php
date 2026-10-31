@php use App\Models\Admin; @endphp
<x-admin-shell title="Admins et accès">
    <x-admin.titre titre="Admins et accès" sous-titre="Comptes du back-office, à part des comptes participants. Connexion par mot de passe puis code reçu par email.">
        <a href="{{ route('admin.utilisateurs.create', ['niveau' => 1]) }}" class="rfl-btn px-5 py-2.5 text-sm">Créer un compte admin</a>
    </x-admin.titre>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        @foreach (Admin::LIBELLES as $niveau => $libelle)
            <div class="rfl-carte p-4">
                <p class="text-sm text-gris-texte">{{ $libelle }}</p>
                <p class="mt-1 font-display text-2xl text-vert" data-test="niveau-{{ $niveau }}">{{ $admins->where('admin.niveau_acces', $niveau)->count() }}</p>
                <p class="text-xs text-gris-texte">{{ [1 => 'Consulte tout, sauf les réponses de santé', 2 => 'Gère inscriptions, dossards, documents et relances', 3 => 'Tous les droits : paramétrage, remboursements, admins'][$niveau] }}</p>
            </div>
        @endforeach
    </div>

    <div class="rfl-carte overflow-hidden">
        <div class="overflow-x-auto">
            <table class="rfl-tableau min-w-[640px]">
                <thead><tr><th>Nom</th><th>Email</th><th>Niveau</th><th>Compte</th><th>Dernière action</th></tr></thead>
                <tbody>
                    @foreach ($admins as $a)
                        <tr class="relative hover:bg-fond/60">
                            <td class="font-bold"><a href="{{ route('admin.utilisateurs.show', $a) }}" class="after:absolute after:inset-0">{{ $a->name }}</a></td>
                            <td>{{ $a->email }}</td>
                            <td><span class="rfl-badge bg-vert-clair text-vert">{{ Admin::LIBELLES[$a->admin->niveau_acces] }}</span></td>
                            <td class="text-sm">{{ $a->mot_de_passe ? 'Activé' : 'En attente d\'activation' }}</td>
                            <td class="text-sm text-gris-texte">{{ isset($dernieres[$a->id_user]) ? \Illuminate\Support\Carbon::parse($dernieres[$a->id_user])->format('d/m/Y H:i') : '–' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-admin-shell>
