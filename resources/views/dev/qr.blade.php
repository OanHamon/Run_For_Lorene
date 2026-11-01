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
                Vrais billets de {{ $evenement?->nom ?? 'la base' }}, codes piégés et un lecteur. Scanne un code avec la caméra,
                ou prends-le en photo. Rien n'est modifié en base.
            </p>
        </header>

        <section class="rfl-carte mb-8 rounded-3xl bg-white p-6" data-lecteur data-verifier="{{ route('dev.qr.verifier') }}" data-csrf="{{ csrf_token() }}">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="font-display text-xl text-vert">Lecteur</h2>
                    <p class="mt-1 text-sm text-gris-texte">Même lecture que le poste de retrait (jsQR), puis vérification par le serveur.</p>
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
                @foreach ($billets as $b)
                    <article class="rfl-carte flex flex-col items-center rounded-3xl bg-white p-6 text-center">
                        <div class="flex items-center gap-4">
                            <div class="rounded-2xl bg-white p-2 ring-1 ring-bordure">{!! $b['qr'] !!}</div>
                            <x-track.empreinte :graine="$b['jeton']" class="w-20" />
                        </div>
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
                        <div class="rounded-2xl bg-white p-2 ring-1 ring-bordure">{!! $p['qr'] !!}</div>
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
