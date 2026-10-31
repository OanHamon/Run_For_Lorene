<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.head', ['title' => 'Retrait des dossards · '.$poste->libelle])
    @vite('resources/js/retrait.js')
    <meta name="robots" content="noindex">
</head>
<body class="bg-fond font-sans text-vert-sombre antialiased">
    <div class="mx-auto max-w-xl px-4 py-5">
        <header class="mb-5 flex items-center justify-between gap-3">
            <img src="/images/logo-run-for-lorene.svg" alt="Run For Lorène" class="h-12 w-auto">
            <div class="text-right text-sm">
                <p class="font-bold">Poste {{ $poste->libelle }}</p>
                <p class="text-gris-texte">{{ $benevole }}</p>
            </div>
        </header>
        @include('retrait._ecran', ['ecran' => $ecran])
        <p class="mt-6 text-center text-xs text-gris-texte">Un souci ? Demande à un membre de l'association.</p>
    </div>
</body>
</html>
