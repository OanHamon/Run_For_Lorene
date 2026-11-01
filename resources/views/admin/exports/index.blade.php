<x-admin-shell title="Exports et listes">
    <x-admin.titre titre="Exports et listes" :sous-titre="$evenement?->nom" />

    @if (! $evenement)
        <div class="rfl-carte px-6 py-10 text-center text-gris-texte">Aucun événement pour l'instant.</div>
    @else
        <p class="mb-6 text-sm text-gris-texte">Fichiers CSV lisibles dans Excel. Ils contiennent des données personnelles : garde-les dans un dossier partagé de l'association, pas sur une clé USB ni dans tes emails. Chaque export est noté au journal.</p>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ([
                ['Inscrits', 'Toutes les inscriptions (filtres de la page Inscriptions), avec contacts d\'urgence.', route('admin.inscriptions.export'), 'Télécharger (CSV)'],
                ['Dossards', 'Inscriptions validées : dossard, nom, course. Pour l\'impression des dossards.', route('admin.inscriptions.dossards'), 'Télécharger (CSV)'],
                ['Retraits des dossards', 'Remis ou non, quand et par qui. À faire en fin de journée.', route('admin.exports.retraits'), 'Télécharger (CSV)'],
                ['Dons', 'Dons de l\'événement, anonymes ou non, reçus demandés.', route('admin.dons.export'), 'Télécharger (CSV)'],
            ] as [$titre, $aide, $url, $bouton])
                <div class="rfl-carte flex flex-col p-5">
                    <h2 class="font-display text-vert">{{ $titre }}</h2>
                    <p class="mt-1 flex-1 text-sm text-gris-texte">{{ $aide }}</p>
                    <a href="{{ $url }}" class="mt-4 rfl-btn-secondaire w-fit px-4 py-2 text-sm">{{ $bouton }}</a>
                </div>
            @endforeach

            <div class="rfl-carte flex flex-col p-5 md:col-span-2">
                <h2 class="font-display text-vert">Listes d'émargement</h2>
                <p class="mt-1 text-sm text-gris-texte">Une page par course, par ordre alphabétique, avec une case à cocher et une colonne signature. À imprimer, ou à enregistrer en PDF depuis la fenêtre d'impression.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('admin.exports.emargement') }}" target="_blank" class="rfl-btn px-4 py-2 text-sm">Toutes les courses</a>
                    @foreach ($courses as $c)
                        <a href="{{ route('admin.exports.emargement', ['course' => $c->id_course]) }}" target="_blank" class="rfl-btn-secondaire px-4 py-2 text-sm">{{ $c->nom }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</x-admin-shell>
