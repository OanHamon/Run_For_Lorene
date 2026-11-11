{{--
    Écran de retrait des dossards, commun à l'admin et aux postes des bénévoles : compteur,
    scan du billet (caméra), recherche par nom ou dossard, fiche trouvée avec « Remettre ».
    $ecran : evenement, compteur, terme, resultats, trouve, routes (recherche, scan, remettre(), annuler()).
--}}
<div class="space-y-5" data-retrait>
    {{-- Compteur --}}
    <div class="flex items-center justify-between gap-4 rounded-3xl bg-vert px-5 py-4 text-white">
        <div>
            <p class="text-sm text-vert-clair">Dossards remis</p>
            <p class="font-display text-3xl text-moutarde" data-test="compteur-retrait">{{ $ecran['compteur']['retires'] }} <span class="text-lg text-white">/ {{ $ecran['compteur']['total'] }}</span></p>
        </div>
        @if ($ecran['compteur']['total'] > 0)
            <p class="font-display text-xl">{{ (int) round($ecran['compteur']['retires'] * 100 / $ecran['compteur']['total']) }} %</p>
        @endif
    </div>

    @if (session('retrait.succes'))
        <p class="rfl-alerte-info text-base font-bold text-vert" role="status">{{ session('retrait.succes') }}</p>
    @endif
    @if (session('retrait.erreur'))
        <p class="rfl-alerte-erreur text-base" role="alert">{{ session('retrait.erreur') }}</p>
    @endif

    {{-- Fiche trouvée (après un scan, une remise ou une annulation) --}}
    @if ($i = $ecran['trouve'])
        @include('retrait._fiche', ['i' => $i, 'grande' => true])
    @endif

    {{-- Scan du billet --}}
    <section class="rounded-3xl bg-white p-5 ring-1 ring-bordure">
        <form method="POST" action="{{ $ecran['routes']['scan'] }}" data-scan-form>
            @csrf
            <input type="hidden" name="jeton" data-scan-jeton>
            <button type="button" class="rfl-btn w-full py-4 text-lg" data-scan-ouvrir>Scanner un billet</button>
            <div class="mt-4 hidden overflow-hidden rounded-2xl bg-vert-sombre" data-scan-zone>
                <video class="max-h-[60vh] w-full object-contain" playsinline muted data-scan-video></video>
            </div>
            <p class="mt-3 hidden text-sm text-gris-texte" data-scan-aide>Vise l'empreinte du billet (les deux semelles en entier dans l'image) ou son QR code. Sans caméra, cherche la personne par son nom ci-dessous.</p>
            <p class="mt-3 hidden rfl-alerte-erreur text-sm" role="alert" data-scan-erreur></p>
            <button type="button" class="mt-3 hidden w-full rfl-btn-secondaire py-3" data-scan-fermer>Fermer la caméra</button>
        </form>
    </section>

    {{-- Recherche --}}
    <section class="rounded-3xl bg-white p-5 ring-1 ring-bordure">
        <form method="GET" action="{{ $ecran['routes']['recherche'] }}" role="search">
            <label for="retrait-q" class="rfl-label">Nom ou numéro de dossard</label>
            <div class="flex gap-2">
                <input id="retrait-q" name="q" type="search" value="{{ $ecran['terme'] }}" autocomplete="off" placeholder="Martin, 1042…" class="rfl-champ">
                <button type="submit" class="rfl-btn shrink-0 px-5">Chercher</button>
            </div>
        </form>

        @if ($ecran['terme'] !== '')
            <div class="mt-4 space-y-3" data-test="resultats-retrait">
                @forelse ($ecran['resultats'] as $r)
                    @include('retrait._fiche', ['i' => $r, 'grande' => false])
                @empty
                    <p class="text-sm text-gris-texte">Aucune personne trouvée. Vérifie l'orthographe, ou essaie le prénom seul.</p>
                @endforelse
            </div>
        @endif
    </section>
</div>
