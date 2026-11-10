{{--
    Gabarit des pages d'erreur (resources/views/errors/*) : même cadre que le tunnel, une seule idée par écran.
    Le code d'erreur est un chiffre clé : moutarde, uniquement sur fond vert profond (charte).
    Aucune dépendance à la session ni à la base : la page doit s'afficher même quand l'application va mal.
--}}
@props([
    'code',
    'titre',
    'accueil' => true,
])

<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.head', ['title' => $titre.' · Run For Lorène'])
    <meta name="robots" content="noindex">
</head>
<body class="relative min-h-screen overflow-x-hidden bg-fond font-sans text-vert-sombre antialiased">
    @include('partials.bandeau-beta')
    <x-charte.tache class="pointer-events-none fixed -top-20 -right-24 h-80 w-80 text-vert-clair" />
    <x-charte.tache class="pointer-events-none fixed -bottom-28 -left-24 h-72 w-72 rotate-180 text-vert-clair/60" />

    <div class="relative flex min-h-screen flex-col items-center px-4 py-6 sm:py-8">
        <a href="{{ url('/') }}" class="mb-6 flex w-full max-w-[480px] items-center p-1">
            <img src="/images/logo-run-for-lorene.svg" alt="Run For Lorène" class="h-16 w-auto">
        </a>

        <main class="w-full max-w-[480px] rounded-[28px] bg-white p-6 text-center ring-1 ring-bordure sm:p-9" data-test="page-erreur">
            <x-charte.cercle disque="bg-vert" arc="border-vert-doux" class="mx-auto mb-8 w-32">
                <span class="font-display text-4xl text-moutarde" aria-hidden="true">{{ $code }}</span>
            </x-charte.cercle>

            <h1 class="font-display text-2xl text-vert sm:text-3xl">{{ $titre }}</h1>

            <div class="mt-4 space-y-3 leading-relaxed">
                {{ $slot }}
            </div>

            @if (isset($actions) || $accueil)
                <div class="mt-8 flex flex-col items-center gap-3">
                    {{ $actions ?? '' }}
                    @if ($accueil)
                        <a href="{{ url('/') }}" class="{{ isset($actions) ? 'rfl-btn-secondaire' : 'rfl-btn' }}">Revenir à l'accueil</a>
                    @endif
                </div>
            @endif

            <p class="mt-8 text-sm text-gris-texte">Erreur {{ $code }}</p>
        </main>

        <img src="/images/pastille-ej.png" alt="Effervescence Jeunes" class="mt-8 h-12 w-12 self-end sm:mr-[calc(50%-240px)]">
    </div>
</body>
</html>
