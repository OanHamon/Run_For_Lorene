@props(['title' => 'Administration'])

@php
    $user = auth()->user();
    // Rubriques du back-office (spec), avec le niveau d'accès qui les ouvre.
    $rubriques = collect([
        ['admin.tableau', 'admin.tableau', 'Tableau de bord', 1],
        ['admin.inscriptions.index', 'admin.inscriptions.*', 'Inscriptions', 1],
        ['admin.questionnaires.index', 'admin.questionnaires.*', 'Questionnaires de santé', 2],
        ['admin.commandes.index', 'admin.commandes.*', 'Commandes et paiements', 1],
        ['admin.dons.index', 'admin.dons.*', 'Dons', 1],
        ['admin.evenements.index', 'admin.evenements.*', 'Événements', 1],
        ['admin.utilisateurs.index', 'admin.utilisateurs.*', 'Utilisateurs', 3],
        ['admin.journal', 'admin.journal', 'Journal', 1],
    ])->filter(fn ($r) => Route::has($r[0]) && $user->peutAdmin($r[3]));
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.head', ['title' => $title.' · Admin Run For Lorène'])
</head>
<body class="bg-fond-admin font-sans text-vert-sombre antialiased">
    {{-- Barre mobile : logo, puis rubriques en pilules défilantes --}}
    {{-- Navigation en vert sombre ; le logo couleur reste sur une pastille blanche (jamais sur du vert, charte). --}}
    <div class="rounded-b-[28px] bg-vert-sombre px-4 pt-3 pb-4 text-white lg:hidden">
        <div class="flex items-center justify-between">
            <a href="{{ route('admin.tableau') }}" class="rounded-2xl bg-white px-3 py-1.5"><img src="/images/logo-run-for-lorene.svg" alt="Run For Lorène" class="h-9 w-auto"></a>
            <a href="{{ route('espace') }}" class="text-sm font-bold underline decoration-vert-doux decoration-2 underline-offset-4 hover:decoration-white">Mon espace</a>
        </div>
        <nav class="-mx-4 mt-3 flex gap-2 overflow-x-auto px-4 text-sm" aria-label="Administration">
            @foreach ($rubriques as [$route, $motif, $libelle])
                <a href="{{ route($route) }}" @if (request()->routeIs($motif)) aria-current="page" @endif class="shrink-0 rounded-full px-4 py-2 {{ request()->routeIs($motif) ? 'bg-vert-clair font-bold text-vert-sombre' : 'ring-1 ring-white/30 hover:bg-white/10' }}">{{ $libelle }}</a>
            @endforeach
        </nav>
    </div>

    <div class="mx-auto flex max-w-[1400px]">
        <aside class="hidden w-72 shrink-0 px-6 py-8 lg:block">
            <div class="sticky top-8 rounded-[28px] bg-vert-sombre px-5 py-7 text-white">
                <a href="{{ route('admin.tableau') }}" class="block rounded-3xl bg-white p-4">
                    <img src="/images/logo-run-for-lorene.svg" alt="Run For Lorène" class="h-20 w-auto">
                </a>

                <div class="mt-8 flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-vert-clair font-display text-vert-sombre">{{ $user->initials() }}</span>
                    <div class="min-w-0">
                        <p class="truncate font-bold">{{ $user->name }}</p>
                        <p class="truncate text-xs text-vert-clair">{{ \App\Models\Admin::LIBELLES[$user->niveauAdmin()] }}</p>
                    </div>
                </div>

                <p class="mt-8 mb-2 px-3 font-display text-vert-clair">Administration</p>
                <nav class="space-y-1 text-sm" aria-label="Administration">
                    @foreach ($rubriques as [$route, $motif, $libelle])
                        <a href="{{ route($route) }}" @if (request()->routeIs($motif)) aria-current="page" @endif class="block rounded-full px-4 py-2 {{ request()->routeIs($motif) ? 'bg-vert-clair font-bold text-vert-sombre' : 'hover:bg-white/10' }}">{{ $libelle }}</a>
                    @endforeach
                </nav>

                <div class="mt-10 space-y-1 border-t border-white/20 pt-6 text-sm">
                    <a href="{{ route('espace') }}" class="block rounded-full px-4 py-2 hover:bg-white/10">Mon espace participant</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full rounded-full px-4 py-2 text-left hover:bg-white/10">Se déconnecter</button>
                    </form>
                </div>

                <img src="/images/pastille-ej.png" alt="Effervescence Jeunes" class="mt-8 ml-auto h-10 w-10">
            </div>
        </aside>

        <main class="min-w-0 flex-1 px-4 py-8 sm:px-8 lg:py-10">
            @if (session('statut'))
                <p class="mb-6 rfl-alerte-info text-sm text-vert" role="status">{{ session('statut') }}</p>
            @endif
            @if (session('erreur'))
                <p class="mb-6 rfl-alerte-erreur" role="alert">{{ session('erreur') }}</p>
            @endif

            {{ $slot }}
        </main>
    </div>
</body>
</html>
