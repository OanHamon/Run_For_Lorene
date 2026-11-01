<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.head', ['title' => 'TRACK · prototype'])
    <meta name="robots" content="noindex">
</head>
<body class="bg-fond font-sans text-vert-sombre antialiased">
    <main class="mx-auto max-w-5xl px-4 py-8">
        <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl text-vert">TRACK</h1>
                <p class="mt-1 text-gris-texte">L'empreinte porte un code : lis-la avec le lecteur de /dev/qr. Survole-la, clique ou touche-la.</p>
            </div>
            <button type="button" class="rfl-btn-secondaire" id="track-pause">Figer (mode scan)</button>
        </header>

        <section class="grid gap-6 md:grid-cols-[1.4fr_1fr]">
            <div class="rfl-carte flex items-center justify-center rounded-3xl bg-white p-8">
                <x-track.empreinte code="RFL-1042-0b1c2d3e4f5a6b7c8d9e" class="w-full max-w-md" />
            </div>

            <div class="flex flex-col gap-6">
                <div class="rfl-carte rounded-3xl bg-white p-6">
                    <h2 class="font-display text-lg text-vert">Une empreinte par personne</h2>
                    <p class="mt-1 text-sm text-gris-texte">Chaque billet colore ses semelles à sa façon : c'est son code.</p>
                    <div class="mt-4 grid grid-cols-3 gap-3">
                        @foreach (['RFL-1043-7c1e9a0b2d4f6e8a1c3b', 'RFL-1044-e5d4c3b2a1f0e9d8c7b6', 'RFL-1045-0123456789abcdef0123'] as $graine)
                            <x-track.empreinte :code="$graine" class="w-full" />
                        @endforeach
                    </div>
                </div>

                <div class="rfl-carte rounded-3xl bg-white p-6">
                    <h2 class="font-display text-lg text-vert">Petits formats et version fixe</h2>
                    <p class="mt-1 text-sm text-gris-texte">160, 96 et 64 px de large ; la dernière est statique (impression).</p>
                    <div class="mt-4 flex items-end gap-5">
                        <x-track.empreinte graine="demo-jade" class="w-40" />
                        <x-track.empreinte graine="demo-jade" class="w-24" />
                        <x-track.empreinte graine="demo-jade" class="w-16" statique />
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
        document.getElementById('track-pause').addEventListener('click', (e) => {
            const figer = e.currentTarget.dataset.fige !== '1';
            e.currentTarget.dataset.fige = figer ? '1' : '0';
            e.currentTarget.textContent = figer ? 'Relancer l\'animation' : 'Figer (mode scan)';
            document.querySelectorAll('[data-track]').forEach((el) => figer ? el.trackEmpreinte?.pause() : el.trackEmpreinte?.reprendre());
        });
    </script>
</body>
</html>
