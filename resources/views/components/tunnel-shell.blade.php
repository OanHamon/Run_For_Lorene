@props([
    'step' => null,
    'label' => null,
    'totalSteps' => 7,
])

<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.head', ['title' => $title ?? 'Run For Lorène'])
    {{ $head ?? '' }}
</head>
<body class="relative min-h-screen overflow-x-hidden bg-fond font-sans text-vert-sombre antialiased">
    <x-charte.tache class="pointer-events-none fixed -top-20 -right-24 h-80 w-80 text-vert-clair" />
    <x-charte.tache class="pointer-events-none fixed -bottom-28 -left-24 h-72 w-72 rotate-180 text-vert-clair/60" />

    <div class="relative flex min-h-screen flex-col items-center px-4 py-6 sm:py-8">
        <a href="{{ route('home') }}" class="mb-6 flex w-full max-w-[480px] items-center p-1">
            <img src="/images/logo-run-for-lorene.svg" alt="Run For Lorène" class="h-16 w-auto">
        </a>

        <div class="w-full max-w-[480px] rounded-[28px] bg-white p-6 ring-1 ring-bordure sm:p-9">
            @if ($step)
                <div class="mb-6">
                    <p class="mb-2 text-sm text-gris-texte">Étape {{ $step }} sur {{ $totalSteps }} @if($label)&middot; {{ $label }}@endif</p>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-vert-clair/70">
                        <div class="h-full rounded-full bg-vert transition-all duration-300" style="width: {{ round($step / $totalSteps * 100) }}%"></div>
                    </div>
                </div>
            @endif

            {{ $slot }}
        </div>
    </div>
    @fluxScripts
</body>
</html>
