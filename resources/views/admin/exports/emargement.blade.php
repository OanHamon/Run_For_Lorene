<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.head', ['title' => 'Émargement · '.$evenement->nom])
    <meta name="robots" content="noindex">
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        @media print { .ecran-seulement { display: none !important; } .page-course { break-after: page; } body { background: white !important; } }
    </style>
</head>
<body class="bg-fond font-sans text-vert-sombre">
    <div class="ecran-seulement sticky top-0 flex items-center justify-between gap-4 bg-vert-sombre px-6 py-3 text-white">
        <p class="text-sm">Liste d'émargement · {{ $evenement->nom }}</p>
        <button type="button" onclick="window.print()" class="rounded-full bg-white px-5 py-2 text-sm font-bold text-vert-sombre">Imprimer ou enregistrer en PDF</button>
    </div>

    @foreach ($courses as ['course' => $course, 'inscrits' => $inscrits])
        <section class="page-course mx-auto max-w-4xl bg-white px-8 py-8">
            <header class="mb-4 flex items-end justify-between gap-4 border-b-2 border-vert pb-3">
                <div>
                    <h1 class="font-display text-2xl text-vert">{{ $course->nom }}</h1>
                    <p class="text-sm">{{ $evenement->nom }} · {{ $evenement->date_evenement->translatedFormat('j F Y') }} · départ {{ \Illuminate\Support\Carbon::parse($course->heure_depart)->format('G\hi') }}</p>
                </div>
                <p class="text-sm">{{ $inscrits->count() }} inscrit(s)</p>
            </header>
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="text-left">
                        <th class="border-b border-bordure py-2 pr-2">Dossard</th>
                        <th class="border-b border-bordure py-2 pr-2">Nom</th>
                        <th class="border-b border-bordure py-2 pr-2">Prénom</th>
                        <th class="border-b border-bordure py-2 pr-2">Âge</th>
                        <th class="border-b border-bordure py-2 pr-2">Présent</th>
                        <th class="w-1/4 border-b border-bordure py-2">Signature</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($inscrits as $i)
                        <tr class="break-inside-avoid">
                            <td class="border-b border-bordure py-2.5 pr-2 font-bold">{{ $i->dossard ?? ($i->statut === \App\Models\Inscription::DOSSIER_INCOMPLET ? 'incomplet' : '–') }}</td>
                            <td class="border-b border-bordure py-2.5 pr-2">{{ $i->participant->user->nom }}</td>
                            <td class="border-b border-bordure py-2.5 pr-2">{{ $i->participant->user->prenom }}</td>
                            <td class="border-b border-bordure py-2.5 pr-2">{{ $i->participant->ageAu($evenement->date_evenement) }}</td>
                            <td class="border-b border-bordure py-2.5 pr-2"><span class="inline-block h-4 w-4 rounded border border-vert-sombre" aria-hidden="true"></span></td>
                            <td class="border-b border-bordure py-2.5"></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-gris-texte">Aucun inscrit.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @endforeach
</body>
</html>
