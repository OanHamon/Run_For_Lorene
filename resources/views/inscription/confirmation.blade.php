@php
    $euros = fn ($m) => number_format((float) $m, fmod((float) $m, 1.0) == 0.0 ? 0 : 2, ',', ' ').' €';
    $incompletes = $commande->inscriptions->where('statut', \App\Models\Inscription::DOSSIER_INCOMPLET);
    $evenement = $commande->inscriptions->first()?->evenement;
@endphp

<x-tunnel-shell :title="($enAttente ? 'Paiement en cours' : 'Confirmation').' · Run For Lorène'">
    @if ($enAttente)
        <x-slot:head>
            {{-- Le webhook Stripe fait foi : on recharge tant qu'il n'est pas arrivé. --}}
            <meta http-equiv="refresh" content="3">
        </x-slot:head>

        <div class="flex flex-col items-center py-6 text-center" role="status">
            <svg class="mb-6 h-14 w-14 animate-spin text-canard" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 3a9 9 0 1 0 9 9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
            </svg>
            <h1 class="font-display text-2xl font-bold text-encre">On confirme ton paiement…</h1>
            <p class="mt-3 text-sm text-gris-texte">Cela ne prend que quelques secondes. Cette page se met à jour toute seule.</p>
            <p class="mt-6 text-xs text-gris-texte">Si rien ne bouge d'ici une minute, retrouve ta commande dans <a href="{{ route('espace') }}" class="rfl-lien">ton espace</a>.</p>
        </div>
    @elseif ($commande->statut === \App\Models\Commande::PAYEE)
        <div class="py-2">
            <div class="mb-6 flex h-14 w-14 items-center justify-center rounded-full bg-canard">
                <svg class="h-7 w-7 text-white" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>

            <h1 class="font-display text-2xl font-bold text-encre">C'est dans la boîte !</h1>
            <p class="mt-3 text-sm text-gris-texte">
                {{ $commande->inscriptions->count() > 1 ? 'Les inscriptions sont enregistrées' : 'Ton inscription est enregistrée' }}{{ $evenement ? ' pour '.$evenement->nom.', le '.$evenement->date_evenement->translatedFormat('l j F Y') : '' }}.
                Un email récapitulatif part vers {{ $commande->user->email }}.
            </p>

            <ul class="mt-6 divide-y divide-bordure rounded-xl border border-bordure">
                @foreach ($commande->inscriptions as $i)
                    <li class="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                        <span>
                            <span class="font-medium text-encre">{{ $i->participant->user->prenom }} {{ $i->participant->user->nom }}</span><br>
                            <span class="text-gris-texte">{{ $i->tarif->course->nom }}</span>
                        </span>
                        @if ($i->statut === \App\Models\Inscription::VALIDEE)
                            <span class="shrink-0 rounded-full bg-aqua-clair px-3 py-1 text-xs font-medium text-canard">{{ $i->dossard ? 'Dossard n° '.$i->dossard : 'Validée' }}</span>
                        @else
                            <span class="shrink-0 rounded-full bg-moutarde/20 px-3 py-1 text-xs font-medium text-encre">{{ $i->libelleStatut() }}</span>
                        @endif
                    </li>
                @endforeach
                @if ($commande->don)
                    <li class="flex items-center justify-between px-4 py-3 text-sm">
                        <span class="text-encre">Don · merci !</span>
                        <span class="font-medium text-encre">{{ $euros($commande->don->montant) }}</span>
                    </li>
                @endif
            </ul>

            @if ($incompletes->isNotEmpty())
                <div class="mt-6 rounded-xl border-2 border-moutarde bg-moutarde/15 px-4 py-3 text-sm text-encre" role="alert">
                    <p class="font-bold">Pas de départ sans ces documents, à remplir depuis ton espace{{ $evenement ? ' avant le '.$evenement->date_evenement->copy()->subDays(15)->translatedFormat('j F Y') : '' }} :</p>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach ($incompletes as $i)
                            <li>{{ $i->participant->user->prenom }} : {{ implode(' et ', $i->documentsManquants()) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ route('inscription.tunnel', ['nouveau' => 1]) }}" class="rfl-lien">Inscrire une autre personne</a>
                <a href="{{ route('espace') }}" class="rfl-btn">Aller à mon espace</a>
            </div>
        </div>
    @else
        <h1 class="font-display text-2xl font-bold text-encre">Commande introuvable</h1>
        <p class="mt-3 text-sm text-gris-texte">Cette commande n'est plus active. Retrouve tes inscriptions dans ton espace.</p>
        <div class="mt-8"><a href="{{ route('espace') }}" class="rfl-btn">Mon espace</a></div>
    @endif
</x-tunnel-shell>
