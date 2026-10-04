@props(['title' => 'Mon espace'])

@php($user = auth()->user())
<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.head', ['title' => $title.' · Run For Lorène'])
</head>
<body class="bg-creme font-sans text-encre antialiased">
    {{-- Barre mobile --}}
    <div class="flex items-center justify-between border-b border-bordure bg-white px-4 py-3 lg:hidden">
        <a href="{{ route('espace') }}" class="font-display text-lg font-bold text-encre">Run for lorène</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm rfl-lien">Se déconnecter</button>
        </form>
    </div>

    <div class="mx-auto flex max-w-7xl">
        <aside class="hidden w-64 shrink-0 border-r border-bordure px-6 py-8 lg:block">
            <a href="{{ route('home') }}" class="font-display text-lg font-bold text-encre">
                Run for lorène
            </a>

            <div class="mt-8 flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-aqua-clair font-display font-bold text-canard">{{ $user->initials() }}</span>
                <div class="min-w-0">
                    <p class="truncate font-medium text-encre">{{ $user->name }}</p>
                    <p class="truncate text-xs text-gris-texte">{{ $user->email }}</p>
                </div>
            </div>

            <p class="mt-8 mb-2 text-xs font-semibold tracking-wide text-gris-texte uppercase">Mon espace</p>
            <nav class="space-y-1 text-sm" aria-label="Espace participant">
                <a href="{{ route('espace') }}" @if (request()->routeIs('espace*')) aria-current="page" @endif class="block rounded-lg px-3 py-2 {{ request()->routeIs('espace*') ? 'bg-aqua-clair font-medium text-canard' : 'text-encre hover:bg-aqua-clair/50' }}">Tableau de bord</a>
            </nav>

            <p class="mt-8 mb-2 text-xs font-semibold tracking-wide text-gris-texte uppercase">Événement</p>
            <nav class="space-y-1 text-sm" aria-label="Événement">
                <a href="{{ route('home') }}" class="block rounded-lg px-3 py-2 text-encre hover:bg-aqua-clair/50">Page de l'événement</a>
                <a href="{{ route('textes.show', 'reglement') }}" target="_blank" rel="noopener" class="block rounded-lg px-3 py-2 text-encre hover:bg-aqua-clair/50">Règlement (PDF)</a>
            </nav>

            <div class="mt-10 border-t border-bordure pt-6 text-sm">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full rounded-lg px-3 py-2 text-left text-encre hover:bg-aqua-clair/50">Se déconnecter</button>
                </form>
            </div>
        </aside>

        <main class="min-w-0 flex-1 px-4 py-8 sm:px-8">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
