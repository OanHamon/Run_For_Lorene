<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.head', ['title' => 'Run For Lorène · RFL3'])
</head>
<body class="bg-white font-sans text-encre antialiased">

    <header class="border-b border-bordure">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
            <a href="{{ route('home') }}">
                <img src="/images/logo-run-for-lorene.svg" alt="Run For Lorène" class="h-14 w-auto">
            </a>

            <nav class="hidden items-center gap-8 text-sm font-medium text-encre md:flex">
                <a href="#evenement" class="hover:text-canard">L'événement</a>
                <a href="#epreuves" class="hover:text-canard">Les épreuves</a>
                <a href="#cause" class="hover:text-canard">La cause</a>
                <a href="#reglement" class="hover:text-canard">Règlement</a>
                <a href="#contact" class="hover:text-canard">Contact</a>
            </nav>

            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('espace') }}" class="rounded-lg bg-canard px-4 py-2 text-sm font-medium text-white hover:bg-profond">Mon espace</a>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-medium text-encre hover:text-canard">Se connecter</a>
                    <a href="{{ route('inscription.tunnel') }}" class="rounded-lg bg-canard px-4 py-2 text-sm font-medium text-white hover:bg-profond">S'inscrire</a>
                @endauth
            </div>
        </div>
    </header>

    <section id="evenement" class="relative overflow-hidden bg-gradient-to-br from-canard to-profond text-white">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 md:grid-cols-2 md:items-center">
            <div>
                <p class="mb-4 text-sm font-semibold tracking-wide text-moutarde uppercase">RFL3 &middot; Samedi 3 avril 2027 &middot; Nantes</p>
                <h1 class="font-display text-4xl leading-tight font-bold sm:text-5xl">Ensemble pour la santé mentale des jeunes.</h1>
                <p class="mt-5 max-w-md text-white/85">Courses et marche ouvertes à toutes et tous, sans chrono. Un événement organisé par Effervescence Jeunes.</p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('inscription.tunnel') }}" class="rounded-lg bg-moutarde px-6 py-3 font-medium text-encre hover:brightness-95">S'inscrire</a>
                    <a href="#cause" class="rounded-lg border border-white/50 px-6 py-3 font-medium text-white hover:bg-white/10">Faire un don</a>
                </div>

                <a href="{{ route('espace') }}" class="mt-6 inline-block text-sm text-white/80 hover:text-white">Déjà inscrit ? Accéder à mon espace →</a>
            </div>

            <div class="flex aspect-video items-center justify-center rounded-lg bg-white/10 text-white/60">
                Photo de l'édition précédente
            </div>
        </div>

        <div class="h-1.5 w-full bg-gradient-to-r from-aqua to-moutarde"></div>
    </section>

    <section class="border-b border-bordure bg-white">
        <div class="mx-auto grid max-w-6xl grid-cols-2 gap-6 px-4 py-8 sm:px-6 md:grid-cols-4">
            <div>
                <p class="text-xs font-semibold tracking-wide text-gris-texte uppercase">Date</p>
                <p class="mt-1 font-display font-bold text-encre">Samedi 3 avril 2027</p>
            </div>
            <div>
                <p class="text-xs font-semibold tracking-wide text-gris-texte uppercase">Lieu</p>
                <p class="mt-1 font-display font-bold text-encre">Nantes</p>
            </div>
            <div>
                <p class="text-xs font-semibold tracking-wide text-gris-texte uppercase">Formats</p>
                <p class="mt-1 font-display font-bold text-encre">4 épreuves, sans chrono</p>
            </div>
            <div>
                <p class="text-xs font-semibold tracking-wide text-gris-texte uppercase">L'an dernier</p>
                <p class="mt-1 font-display font-bold text-encre">23 000 € collectés</p>
            </div>
        </div>
    </section>

    <section id="epreuves" class="bg-creme py-16">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="mb-1 text-sm font-semibold tracking-wide text-moutarde uppercase">Les épreuves</p>
                    <h2 class="font-display text-3xl font-bold text-encre">Choisis ton format</h2>
                </div>
                <a href="{{ route('textes.show', 'reglement') }}" target="_blank" rel="noopener" class="rounded-lg border border-bordure bg-white px-4 py-2 text-sm font-medium text-encre hover:border-canard">Télécharger le règlement (PDF)</a>
            </div>

            <div class="overflow-x-auto rounded-lg border border-bordure bg-white">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="border-b border-bordure bg-creme/60 text-xs font-semibold tracking-wide text-gris-texte uppercase">
                        <tr>
                            <th class="px-6 py-3">Épreuve</th>
                            <th class="px-6 py-3">Départ</th>
                            <th class="px-6 py-3">Public</th>
                            <th class="px-6 py-3">Tarif</th>
                            <th class="px-6 py-3">Places</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-bordure">
                        @forelse ($courses as $course)
                            @php
                                $restantes = $course->placesRestantes();
                                $complet = $restantes !== null && $restantes <= 0;
                                $prix = $course->tarifs->filter(fn ($t) => $t->valide_du->lte(today()) && $t->valide_au->gte(today()))->min('montant');
                                $public = match (true) {
                                    $course->age_min && $course->age_max => "{$course->age_min} à {$course->age_max} ans",
                                    (bool) $course->age_min => "Dès {$course->age_min} ans",
                                    (bool) $course->age_max => "Jusqu'à {$course->age_max} ans",
                                    default => 'Tout public',
                                };
                            @endphp
                            <tr @class(['opacity-50' => $complet])>
                                <td class="px-6 py-4">
                                    <p class="font-display font-bold text-encre">{{ $course->nom }}</p>
                                    @if ($course->distance)<p class="text-gris-texte">{{ rtrim(rtrim(number_format((float) $course->distance, 2, ',', ''), '0'), ',') }} km</p>@endif
                                </td>
                                <td class="px-6 py-4 text-encre">{{ \Illuminate\Support\Carbon::parse($course->heure_depart)->format('G\hi') }}</td>
                                <td class="px-6 py-4 text-encre">{{ $public }}</td>
                                <td class="px-6 py-4 font-medium {{ $complet ? 'text-encre' : 'text-canard' }}">{{ $prix !== null ? number_format((float) $prix, fmod((float) $prix, 1.0) == 0.0 ? 0 : 2, ',', ' ').' €' : '–' }}</td>
                                <td class="px-6 py-4 text-encre">
                                    @if ($complet)
                                        Complet
                                    @elseif ($restantes === null)
                                        Sans limite
                                    @elseif ($restantes <= 10 || $restantes / max($course->quota, 1) < 0.2)
                                        <span class="font-medium text-encre">Plus que {{ $restantes }} {{ $restantes > 1 ? 'places' : 'place' }} !</span>
                                    @else
                                        {{ $restantes }} places
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    @if ($complet)
                                        <span class="inline-block cursor-not-allowed rounded-lg bg-bordure px-4 py-2 text-sm font-medium text-gris-texte">Complet</span>
                                    @elseif ($ouvert)
                                        <a href="{{ route('inscription.tunnel') }}" class="rounded-lg bg-canard px-4 py-2 text-sm font-medium text-white hover:bg-profond">Choisir</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-8 text-center text-gris-texte">Les épreuves seront bientôt annoncées.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <p class="mt-4 text-sm text-gris-texte">L'âge pris en compte est celui du participant le jour de l'événement.</p>
        </div>
    </section>

    <section id="cause" class="bg-white py-16">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 md:grid-cols-2 md:items-center">
            <div class="flex aspect-[4/3] items-center justify-center rounded-lg bg-aqua-clair text-canard/70">
                Photo / visuel de la cause
            </div>
            <div>
                <img src="/images/logo-effervescence-jeunes.png" alt="Effervescence Jeunes" class="mb-6 h-20 w-auto">
                <p class="mb-2 text-sm font-semibold tracking-wide text-moutarde uppercase">La cause</p>
                <h2 class="font-display text-3xl font-bold text-encre">Pourquoi on court</h2>
                <p class="mt-4 text-gris-texte">Qui était Lorène, pourquoi la santé mentale des jeunes, à quoi sert l'argent collecté : le mot de l'association vient ici.</p>
                <div class="mt-6 flex flex-wrap items-center gap-4">
                    <a href="#" class="rounded-lg bg-canard px-6 py-3 font-medium text-white hover:bg-profond">Faire un don</a>
                    <a href="https://effervescence-jeunes.fr/" class="text-sm font-medium text-canard hover:underline">Découvrir Effervescence Jeunes</a>
                </div>
            </div>
        </div>
    </section>

    <footer id="contact" class="bg-profond text-white/80">
        <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
            <div class="grid gap-8 sm:grid-cols-2 md:grid-cols-4">
                <div>
                    <p class="font-display text-lg font-bold text-white">Run <span class="italic text-moutarde">for Lorène</span></p>
                    <p class="mt-2 text-sm">Un événement Effervescence Jeunes. Ensemble pour la santé mentale des jeunes.</p>
                </div>
                <div>
                    <p class="mb-3 text-xs font-semibold tracking-wide text-moutarde uppercase">Événement</p>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#epreuves" class="hover:text-white">Les épreuves</a></li>
                        <li id="reglement"><a href="{{ route('textes.show', 'reglement') }}" target="_blank" rel="noopener" class="hover:text-white">Règlement</a></li>
                        <li><a href="#" class="hover:text-white">FAQ</a></li>
                    </ul>
                </div>
                <div>
                    <p class="mb-3 text-xs font-semibold tracking-wide text-moutarde uppercase">Participant</p>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('espace') }}" class="hover:text-white">Mon espace</a></li>
                        <li><a href="#cause" class="hover:text-white">Faire un don</a></li>
                        <li><a href="#contact" class="hover:text-white">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <p class="mb-3 text-xs font-semibold tracking-wide text-moutarde uppercase">Contact</p>
                    <ul class="space-y-2 text-sm">
                        <li>contact@effervescence-jeunes.fr</li>
                        <li>effervescence-jeunes.fr</li>
                    </ul>
                </div>
            </div>

            <div class="mt-10 flex flex-wrap items-center gap-x-6 gap-y-2 border-t border-white/10 pt-6 text-xs text-white/60">
                <span>N° RNA W442030950</span>
                <a href="#" class="hover:text-white">Mentions légales</a>
                <a href="#" class="hover:text-white">CGV</a>
                <a href="#" class="hover:text-white">Confidentialité</a>
                <a href="#" class="hover:text-white">Cookies</a>
                <a href="https://effervescence-jeunes.fr/" class="ml-auto" title="Effervescence Jeunes">
                    <img src="/images/pastille-ej.png" alt="Effervescence Jeunes" class="h-10 w-10">
                </a>
            </div>
        </div>
    </footer>

</body>
</html>
