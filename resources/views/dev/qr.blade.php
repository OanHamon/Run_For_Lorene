<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.head', ['title' => 'QR codes · banc d\'essai'])
    <meta name="robots" content="noindex">
    @vite('resources/js/dev-qr.js')
</head>
<body class="bg-fond font-sans text-vert-sombre antialiased">
    <main class="mx-auto max-w-6xl px-4 py-8">
        <header class="mb-8">
            <h1 class="font-display text-3xl text-vert">QR codes : banc d'essai</h1>
            <p class="mt-1 text-gris-texte">
                Vrais billets de {{ $evenement?->nom ?? 'la base' }} : les semelles de chaque empreinte portent le code du billet.
                Lis-les avec la caméra ou une photo (seul ce lecteur et le poste de retrait les comprennent, pas l'appareil
                photo du téléphone). Codes piégés plus bas. Rien n'est modifié en base.
            </p>
        </header>

        <section class="rfl-carte mb-8 rounded-3xl bg-white p-6" data-lecteur data-verifier="{{ route('dev.qr.verifier') }}" data-csrf="{{ csrf_token() }}">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="font-display text-xl text-vert">Lecteur</h2>
                    <p class="mt-1 text-sm text-gris-texte">Même lecture que le poste de retrait (semelles et QR classique), puis vérification par le serveur.</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <button type="button" class="rfl-btn" data-lecteur-camera>Ouvrir la caméra</button>
                    <button type="button" class="rfl-btn-secondaire hidden" data-lecteur-arreter>Arrêter</button>
                    <label class="rfl-btn-secondaire cursor-pointer">
                        Lire une photo
                        <input type="file" accept="image/*" capture="environment" class="sr-only" data-lecteur-photo>
                    </label>
                </div>
            </div>

            <video class="mt-4 hidden aspect-video w-full max-w-md rounded-2xl bg-vert-sombre object-cover" playsinline muted data-lecteur-video></video>
            <p class="rfl-alerte mt-4 hidden" data-lecteur-erreur></p>
            <p class="mt-3 hidden font-mono text-xs text-gris-texte" data-lecteur-etat></p>

            <div class="mt-4 hidden rounded-2xl p-4 ring-1" data-lecteur-resultat>
                <p class="font-display text-lg" data-resultat-message></p>
                <p class="mt-1 text-sm" data-resultat-fiche></p>
                <p class="mt-2 break-all font-mono text-xs text-gris-texte" data-resultat-code></p>
            </div>
        </section>

        <section class="mb-8">
            <h2 class="mb-4 font-display text-xl text-vert">Billets valides</h2>
            @if ($billets->isEmpty())
                <p class="rfl-alerte">Aucune inscription validée avec dossard. Lance <code>php artisan migrate:fresh --seed</code>.</p>
            @endif
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($billets as $i => $b)
                    <article class="rfl-carte flex flex-col items-center rounded-3xl bg-white p-6 text-center">
                        <div id="empreinte-{{ $i }}" class="flex w-full items-center justify-center bg-white">
                            <x-track.empreinte :code="$b['jeton']" class="w-full max-w-80 [:fullscreen_&]:h-full [:fullscreen_&]:w-auto [:fullscreen_&]:max-w-none" />
                        </div>
                        <button type="button" class="rfl-btn-secondaire mt-3 text-sm" data-plein-ecran="empreinte-{{ $i }}">Plein écran</button>
                        <details class="mt-2 text-sm">
                            <summary class="cursor-pointer text-gris-texte">QR classique (témoin)</summary>
                            <div class="mx-auto mt-2 w-fit rounded-2xl bg-white p-2 ring-1 ring-bordure">{!! $b['qr'] !!}</div>
                        </details>
                        <h3 class="mt-4 font-display text-lg text-vert">{{ $b['titre'] }}</h3>
                        <p class="text-sm text-gris-texte">{{ $b['detail'] }}</p>
                        <p class="mt-2 break-all font-mono text-xs text-gris-texte">{{ $b['jeton'] }}</p>
                        <a href="{{ $b['lien'] }}" class="rfl-lien mt-3 text-sm" target="_blank">Ouvrir le billet</a>
                    </article>
                @endforeach
            </div>
        </section>

        <section>
            <h2 class="mb-4 font-display text-xl text-vert">Codes piégés</h2>
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($pieges as $p)
                    <article class="rfl-carte flex flex-col items-center rounded-3xl bg-white p-6 text-center">
                        @if (str_starts_with($p['jeton'], 'RFL-'))
                            <x-track.empreinte :code="$p['jeton']" class="w-full max-w-72" />
                        @else
                            <div class="rounded-2xl bg-white p-2 ring-1 ring-bordure">{!! $p['qr'] !!}</div>
                        @endif
                        <h3 class="mt-4 font-display text-lg text-vert">{{ $p['titre'] }}</h3>
                        <p class="text-sm text-gris-texte">{{ $p['detail'] }}</p>
                        <p class="mt-2 break-all font-mono text-xs text-gris-texte">{{ $p['jeton'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    </main>
</body>
</html>
