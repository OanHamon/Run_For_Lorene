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
<body class="relative min-h-screen overflow-x-hidden bg-creme font-sans text-encre antialiased">
    <div class="pointer-events-none fixed -top-24 -right-24 h-80 w-80 rounded-full bg-aqua-clair"></div>

    <div class="relative flex min-h-screen flex-col items-center px-4 py-6 sm:py-8">
        <a href="{{ route('home') }}" class="mb-6 flex w-full max-w-[460px] items-center">
            <img src="/images/logo-run-for-lorene.svg" alt="Run For Lorène" class="h-16 w-auto">
        </a>

        <div class="w-full max-w-[460px] rounded-2xl bg-white p-6 shadow-[0_1px_2px_rgba(22,64,59,0.06),0_8px_24px_rgba(22,64,59,0.08)] sm:p-8">
            @if ($step)
                <div class="mb-6">
                    <p class="mb-2 text-sm text-gris-texte">Étape {{ $step }} sur {{ $totalSteps }} @if($label)&middot; {{ $label }}@endif</p>
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-bordure">
                        <div class="h-full rounded-full bg-canard transition-all duration-300" style="width: {{ round($step / $totalSteps * 100) }}%"></div>
                    </div>
                </div>
            @endif

            {{ $slot }}
        </div>
    </div>
    @fluxScripts
</body>
</html>
