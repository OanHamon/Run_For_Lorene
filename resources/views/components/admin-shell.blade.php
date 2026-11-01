@props(['title' => 'Administration'])

@php
    use App\Support\EvenementAdmin;
    use App\Support\NavigationAdmin;

    $user = auth()->user();
    $evenementSuivi = EvenementAdmin::choisi(request());
    $evenementsSuivis = NavigationAdmin::evenements();
    $compteurs = app(\App\Services\Admin\ATraiter::class)->compteurs($evenementSuivi, $user);
    $sections = NavigationAdmin::sections(request(), $user, $evenementSuivi, [
        'admin.a-traiter' => $compteurs['total'] ?: null,
        'admin.questionnaires.index' => $compteurs['sante'] ?: null,
    ]);
    $fil = NavigationAdmin::fil($sections, $title);
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.head', ['title' => $title.' · Admin Run For Lorène'])
    {{ $head ?? '' }}
</head>
<body class="bg-fond-admin font-sans text-vert-sombre antialiased">
    {{-- Navigation en vert sombre ; le logo couleur reste sur une pastille blanche (jamais sur du vert, charte). --}}

    {{-- Barre mobile : logo, événement, recherche, puis rubriques en pilules défilantes --}}
    <div class="rounded-b-[28px] bg-vert-sombre px-4 pt-3 pb-4 text-white lg:hidden">
        <div class="flex items-center justify-between">
            <a href="{{ route('admin.tableau') }}" class="rounded-2xl bg-white px-3 py-1.5"><img src="/images/logo-run-for-lorene.svg" alt="Run For Lorène" class="h-9 w-auto"></a>
            <form method="POST" action="{{ route('admin.deconnexion') }}">
                @csrf
                <button type="submit" class="text-sm font-bold underline decoration-vert-doux decoration-2 underline-offset-4 hover:decoration-white">Se déconnecter</button>
            </form>
        </div>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
            @include('components.admin.partials.evenement-suivi', ['id' => 'evenement-suivi-mobile'])
            @include('components.admin.partials.recherche', ['id' => 'recherche-mobile'])
        </div>
        <nav class="-mx-4 mt-3 flex gap-2 overflow-x-auto px-4 text-sm" aria-label="Administration">
            @foreach ($sections as $s)
                @foreach ($s['rubriques'] as $r)
                    <a href="{{ $r['url'] }}" @if ($r['actif']) aria-current="page" @endif class="flex shrink-0 items-center gap-2 rounded-full px-4 py-2 {{ $r['actif'] ? 'bg-vert-clair font-bold text-vert-sombre' : 'ring-1 ring-white/30 hover:bg-white/10' }}">
                        {{ $r['libelle'] }}
                        @if ($r['compteur'])<span class="rounded-full bg-moutarde px-2 text-xs font-bold text-vert-sombre">{{ $r['compteur'] }}</span>@endif
                    </a>
                @endforeach
            @endforeach
        </nav>
    </div>

    <div class="mx-auto flex max-w-[1400px]">
        <aside class="hidden w-72 shrink-0 px-6 py-8 lg:block">
            <div class="sticky top-6 max-h-[calc(100vh-3rem)] overflow-y-auto rounded-[28px] bg-vert-sombre px-5 py-6 text-white">
                <a href="{{ route('admin.tableau') }}" class="block rounded-3xl bg-white p-3">
                    <img src="/images/logo-run-for-lorene.svg" alt="Run For Lorène" class="h-12 w-auto">
                </a>

                <div class="mt-6 flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-vert-clair font-display text-vert-sombre">{{ $user->initials() }}</span>
                    <div class="min-w-0">
                        <p class="truncate font-bold">{{ $user->name }}</p>
                        <p class="truncate text-xs text-vert-clair">{{ \App\Models\Admin::LIBELLES[$user->niveauAdmin()] }}</p>
                    </div>
                </div>

                <div class="mt-6 space-y-2">
                    @include('components.admin.partials.evenement-suivi', ['id' => 'evenement-suivi'])
                    @include('components.admin.partials.recherche', ['id' => 'recherche'])
                </div>

                @foreach ($sections as $s)
                    <p class="mt-5 mb-1 px-3 font-display text-sm text-vert-clair">{{ $s['section'] }}</p>
                    <nav class="space-y-0.5 text-sm" aria-label="{{ $s['section'] }}">
                        @foreach ($s['rubriques'] as $r)
                            <a href="{{ $r['url'] }}" @if ($r['actif']) aria-current="page" @endif class="flex items-center justify-between gap-2 rounded-full px-4 py-1.5 {{ $r['actif'] ? 'bg-vert-clair font-bold text-vert-sombre' : 'hover:bg-white/10' }}">
                                <span>{{ $r['libelle'] }}</span>
                                @if ($r['compteur'])<span class="rounded-full bg-moutarde px-2 text-xs font-bold text-vert-sombre" aria-label="{{ $r['compteur'] }} à traiter">{{ $r['compteur'] }}</span>@endif
                            </a>
                        @endforeach
                    </nav>
                @endforeach

                <div class="mt-8 space-y-1 border-t border-white/20 pt-6 text-sm">
                    <a href="{{ route('home') }}" class="block rounded-full px-4 py-2 hover:bg-white/10">Voir le site</a>
                    <form method="POST" action="{{ route('admin.deconnexion') }}">
                        @csrf
                        <button type="submit" class="block w-full rounded-full px-4 py-2 text-left hover:bg-white/10">Se déconnecter</button>
                    </form>
                </div>

                <img src="/images/pastille-ej.png" alt="Effervescence Jeunes" class="mt-8 ml-auto h-10 w-10">
            </div>
        </aside>

        <main class="min-w-0 flex-1 px-4 py-8 sm:px-8 lg:py-10">
            @if (count($fil) > 0)
                <nav aria-label="Fil d'Ariane" class="mb-3 text-sm text-gris-texte">
                    <ol class="flex flex-wrap items-center gap-x-2">
                        @foreach ($fil as $etape)
                            <li class="flex items-center gap-2">
                                @if (! $loop->first)<span aria-hidden="true">›</span>@endif
                                @if ($etape['url'] && ! $loop->last)
                                    <a href="{{ $etape['url'] }}" class="hover:text-vert hover:underline">{{ $etape['libelle'] }}</a>
                                @else
                                    <span @if ($loop->last) aria-current="page" class="text-vert-sombre" @endif>{{ $etape['libelle'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @endif

            @if (session('statut'))
                <p class="mb-6 rfl-alerte-info text-sm text-vert" role="status">{{ session('statut') }}</p>
            @endif
            @if (session('erreur'))
                <p class="mb-6 rfl-alerte-erreur" role="alert">{{ session('erreur') }}</p>
            @endif

            {{ $slot }}
        </main>
    </div>
    {{ $scripts ?? '' }}
</body>
</html>
