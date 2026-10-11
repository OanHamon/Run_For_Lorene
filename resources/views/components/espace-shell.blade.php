@props(['title' => 'Mon espace'])

@php($user = auth()->user())
<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.head', ['title' => $title.' · Run For Lorène'])
</head>
<body class="bg-fond font-sans text-vert-sombre antialiased">
    {{-- Barre mobile --}}
    <div class="flex items-center justify-between rounded-b-[28px] bg-white px-5 py-3 ring-1 ring-bordure lg:hidden">
        <a href="{{ route('espace') }}"><img src="/images/logo-run-for-lorene.svg" alt="Run For Lorène" class="h-10 w-auto"></a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm rfl-lien">Se déconnecter</button>
        </form>
    </div>

    <div class="mx-auto flex max-w-7xl">
        <aside class="hidden w-72 shrink-0 px-6 py-8 lg:block">
            <div class="sticky top-8 rounded-[28px] bg-white px-5 py-7 ring-1 ring-bordure">
            <a href="{{ route('home') }}" class="inline-block p-1">
                <img src="/images/logo-run-for-lorene.svg" alt="Run For Lorène" class="h-20 w-auto">
            </a>

            <div class="mt-8 flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-vert-clair font-display text-vert">{{ $user->initials() }}</span>
                <div class="min-w-0">
                    <p class="truncate font-bold text-vert-sombre">{{ $user->name }}</p>
                    <p class="truncate text-xs text-gris-texte">{{ $user->email }}</p>
                </div>
            </div>

            <p class="mt-8 mb-2 px-3 font-display text-vert">Mon espace</p>
            <nav class="space-y-1 text-sm" aria-label="Espace participant">
                <a href="{{ route('espace') }}" @if (request()->routeIs('espace*')) aria-current="page" @endif class="block rounded-full px-4 py-2 {{ request()->routeIs('espace*') ? 'bg-vert-clair font-bold text-vert-sombre' : 'text-vert-sombre hover:bg-fond' }}">Tableau de bord</a>
            </nav>

            <p class="mt-8 mb-2 px-3 font-display text-vert">Événement</p>
            <nav class="space-y-1 text-sm" aria-label="Événement">
                <a href="{{ route('home') }}" class="block rounded-full px-4 py-2 text-vert-sombre hover:bg-fond">Page de l'événement</a>
                <a href="{{ route('textes.show', 'reglement') }}" target="_blank" rel="noopener" class="block rounded-full px-4 py-2 text-vert-sombre hover:bg-fond">Règlement (PDF)</a>
            </nav>

            <div class="mt-10 border-t border-bordure pt-6 text-sm">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full rounded-full px-4 py-2 text-left text-vert-sombre hover:bg-fond">Se déconnecter</button>
                </form>
            </div>

            <img src="/images/pastille-ej.png" alt="Effervescence Jeunes" class="mt-8 ml-auto h-10 w-10">
            </div>
        </aside>

        <main class="min-w-0 flex-1 px-4 py-8 sm:px-8 lg:py-10">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
