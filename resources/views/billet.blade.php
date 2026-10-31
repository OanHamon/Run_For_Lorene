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
            <p class="mt-1 text-sm text-vert-sombre">{{ $course->nom }} · départ {{ \Illuminate\Support\Carbon::parse($course->heure_depart)->format('G\hi') }}</p>

            <div class="mx-auto mt-6 w-fit rounded-3xl bg-white p-4 ring-1 ring-bordure" aria-label="QR code du billet">{!! $qr !!}</div>

            @if ($inscription->dossard)
                <div class="mx-auto mt-6 w-fit rounded-3xl bg-vert px-8 py-4">
                    <p class="text-sm text-white/85">Dossard</p>
                    <p class="font-display text-4xl text-moutarde"><span class="sr-only">n° </span>{{ $inscription->dossard }}</p>
                </div>
            @endif

            <p class="mt-6 text-sm text-gris-texte">Présente ce QR code au retrait des dossards. Sans téléphone, donne simplement ton nom.</p>
        </div>
    @endif
</x-tunnel-shell>
