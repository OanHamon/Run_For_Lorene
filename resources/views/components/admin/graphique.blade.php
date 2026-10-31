@props(['titre', 'question', 'config' => null, 'hauteur' => 'h-72', 'vide' => false])

{{-- Carte de graphique : la question à laquelle il répond, le dessin, puis les valeurs en tableau. --}}
<section {{ $attributes->merge(['class' => 'rfl-carte p-6']) }}>
    <h2 class="font-display text-lg text-vert">{{ $titre }}</h2>
    <p class="mt-0.5 mb-4 text-sm text-gris-texte">{{ $question }}</p>

    @if ($vide)
        <p class="rounded-2xl bg-fond px-4 py-8 text-center text-sm text-gris-texte">Pas encore de données.</p>
    @else
        @if ($config)
            <div class="relative {{ $hauteur }}">
                <canvas data-graphique="{{ json_encode($config) }}" role="img" aria-label="{{ $titre }} : valeurs dans le tableau ci-dessous"></canvas>
            </div>
        @endif

        @isset($valeurs)
            <details class="mt-4 text-sm" @unless ($config) open @endunless>
                <summary class="cursor-pointer text-vert hover:underline">{{ $config ? 'Voir les valeurs' : 'Valeurs' }}</summary>
                <div class="mt-3 overflow-x-auto">{{ $valeurs }}</div>
            </details>
        @endisset

        {{ $slot }}
    @endif
</section>
