<x-tunnel-shell :title="'Billet · Run For Lorène'">
    @if (! $valide)
        <h1 class="font-display text-2xl text-vert">Billet indisponible</h1>
        <p class="mt-2 text-sm text-gris-texte">Le billet n'existe que pour une inscription validée. Ouvre ton espace pour voir où en est ton dossier.</p>
        <a href="{{ route('espace') }}" class="mt-6 inline-block rfl-btn">Ouvrir mon espace</a>
    @else
        @php($course = $inscription->tarif->course)
        <div class="text-center">
            <p class="text-sm text-gris-texte">{{ $inscription->evenement->nom }} · {{ $inscription->evenement->date_evenement->translatedFormat('l j F Y') }}</p>
            <h1 class="mt-1 font-display text-2xl text-vert">{{ $inscription->participant->user->prenom }} {{ $inscription->participant->user->nom }}</h1>
            <p class="mt-1 flex items-center justify-center gap-2 text-sm text-vert-sombre"><x-course-couleur :course="$course" />{{ $course->nom }} · départ {{ \Illuminate\Support\Carbon::parse($course->heure_depart)->format('G\hi') }}</p>

            {{-- L'empreinte est le billet : ses semelles portent le jeton, lu au poste de retrait. --}}
            <div class="mx-auto mt-6 w-full max-w-sm rounded-3xl bg-white p-4 ring-1 ring-bordure">
                <x-track.empreinte :code="$jeton" class="w-full" libelle="Ton empreinte : le billet à présenter au retrait des dossards" />
            </div>
            <p class="mt-3 text-sm text-vert-sombre">Au retrait, montre ton empreinte : le ou la bénévole la scanne. Monte la luminosité de ton écran.</p>

            <details class="mx-auto mt-4 max-w-sm text-sm">
                <summary class="cursor-pointer text-gris-texte">Le scan ne passe pas ? Afficher le QR code</summary>
                <div class="mx-auto mt-3 w-fit rounded-3xl bg-white p-4 ring-1 ring-bordure" aria-label="QR code du billet">{!! $qr !!}</div>
            </details>

            @if ($inscription->dossard)
                <div class="mx-auto mt-6 w-fit rounded-3xl bg-vert px-8 py-4">
                    <p class="text-sm text-white/85">Dossard</p>
                    <p class="font-display text-4xl text-moutarde"><span class="sr-only">n° </span>{{ $inscription->dossard }}</p>
                </div>
            @endif

            <a href="{{ \App\Support\Billet::urlPdf($inscription) }}" class="mt-6 inline-block rfl-btn-secondaire" data-test="billet-pdf">Télécharger le billet à imprimer (PDF)</a>

            <p class="mt-6 text-sm text-gris-texte">Sans téléphone, donne simplement ton nom ou ton numéro de dossard.</p>
        </div>
    @endif
</x-tunnel-shell>
