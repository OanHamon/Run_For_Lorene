<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.head', ['title' => 'Run For Lorène · RFL3'])
</head>
<body class="bg-white font-sans text-vert-sombre antialiased">

    <header class="bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-6 px-5 py-4 sm:px-8">
            <a href="{{ route('home') }}" class="shrink-0 p-1">
                <img src="/images/logo-run-for-lorene.svg" alt="Run For Lorène, retour à l'accueil" class="h-16 w-auto">
            </a>

            <nav class="hidden items-center gap-7 font-display text-vert lg:flex" aria-label="Sections de la page">
                <a href="#epreuves" class="hover:text-vert">Les épreuves</a>
                <a href="#cause" class="hover:text-vert">La cause</a>
                <a href="{{ route('textes.show', 'reglement') }}" target="_blank" rel="noopener" class="hover:text-vert">Règlement</a>
                <a href="#contact" class="hover:text-vert">Contact</a>
            </nav>

            <div class="flex items-center gap-4">
                @auth
                    <a href="{{ route('espace') }}" class="rfl-btn px-5 py-2.5">Mon espace</a>
                @else
                    <a href="{{ route('login') }}" class="hidden font-display text-vert hover:text-vert sm:inline">Se connecter</a>
                    <a href="{{ route('inscription.tunnel') }}" class="rfl-btn px-5 py-2.5">S'inscrire</a>
                @endauth
            </div>
        </div>
    </header>

    {{-- Héro : aplat vert profond, la date en moutarde comme dans le bloc événement des signatures de la charte. --}}
    <section id="evenement" class="relative overflow-hidden bg-vert text-white">
        <div class="mx-auto grid max-w-6xl gap-12 px-5 pt-14 pb-24 sm:px-8 md:grid-cols-[1.15fr_1fr] md:items-center md:pt-20 md:pb-32">
            <div>
                <div class="flex items-end gap-4">
                    <p class="font-display leading-none">
                        <span class="block text-7xl text-moutarde sm:text-8xl">03</span>
                    </p>
                    <p class="pb-1.5 font-display leading-tight">
                        <span class="block text-2xl text-moutarde">avril</span>
                        <span class="block tracking-[0.3em] text-white/90">2027</span>
                    </p>
                    <p class="mb-1.5 ml-2 border-l-2 border-vert-doux pl-4 leading-snug text-white/90">
                        Samedi à Nantes<br>3<sup>e</sup> édition
                    </p>
                </div>

                <h1 class="mt-8 font-display text-4xl leading-[1.15] sm:text-5xl">Ensemble pour la santé mentale des jeunes</h1>
                <p class="mt-6 max-w-md text-lg leading-relaxed text-white/90">Courses et marche ouvertes à toutes et tous, sans chrono. Un événement organisé par Effervescence Jeunes.</p>

                <div class="mt-9 flex flex-wrap items-center gap-3">
                    <a href="{{ route('inscription.tunnel') }}" class="rfl-btn-moutarde text-lg">Je m'inscris</a>
                    <a href="#cause" class="inline-flex items-center rounded-full border-2 border-white/70 px-7 py-3 font-display text-white hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Faire un don</a>
                </div>

                <p class="mt-7 text-white/85">Déjà inscrit ? <a href="{{ route('espace') }}" class="font-bold text-white underline decoration-moutarde decoration-2 underline-offset-4">Retrouve ton espace</a></p>
            </div>

            {{-- Le logo couleur ne se pose pas sur du vert : il vit dans le disque blanc, comme dans les diaporamas de la charte. --}}
            <div class="relative mx-auto w-full max-w-[250px] sm:max-w-[340px] md:max-w-[400px]">
                <img src="/images/etoile-filante.png" alt="" class="pointer-events-none absolute -top-8 -left-12 w-36 sm:-top-10 sm:-left-16 sm:w-56" aria-hidden="true">
                <x-charte.cercle arc="border-vert-doux">
                    <img src="/images/logo-run-for-lorene.svg" alt="Run For Lorène" class="w-[68%]">
                </x-charte.cercle>
            </div>
        </div>

        <x-charte.colline class="absolute inset-x-0 -bottom-px h-12 text-white sm:h-20" />
    </section>

    {{-- L'essentiel en quatre repères, chacun avec son picto de la charte. --}}
    <section class="bg-white" aria-label="L'essentiel">
        <dl class="mx-auto grid max-w-6xl grid-cols-1 gap-x-8 gap-y-7 px-5 pt-4 pb-14 sm:grid-cols-2 sm:px-8 lg:grid-cols-4">
            <div class="flex items-center gap-4">
                <img src="/images/pictos/soleil.png" alt="" class="h-14 w-14 shrink-0">
                <div><dt class="text-gris-texte">Quand</dt><dd class="font-display text-lg text-vert">Samedi 3 avril 2027</dd></div>
            </div>
            <div class="flex items-center gap-4">
                <img src="/images/pictos/evenement.png" alt="" class="h-14 w-14 shrink-0">
                <div><dt class="text-gris-texte">Où</dt><dd class="font-display text-lg text-vert">Nantes</dd></div>
            </div>
            <div class="flex items-center gap-4">
                <img src="/images/pictos/collectif.png" alt="" class="h-14 w-14 shrink-0">
                <div><dt class="text-gris-texte">Format</dt><dd class="font-display text-lg text-vert">4 épreuves, sans chrono</dd></div>
            </div>
            <div class="flex items-center gap-4">
                <img src="/images/pictos/don.png" alt="" class="h-14 w-14 shrink-0">
                <div><dt class="text-gris-texte">L'an dernier</dt><dd class="font-display text-lg text-vert">23 000 € collectés</dd></div>
            </div>
        </dl>
    </section>

    <section id="epreuves" class="bg-fond py-16 sm:py-20">
        <div class="mx-auto max-w-6xl px-5 sm:px-8">
            <div class="mb-8 flex flex-wrap items-end justify-between gap-5">
                <div>
                    <h2 class="rfl-ruban text-2xl">Les épreuves</h2>
                    <p class="mt-4 text-lg text-vert-sombre">Choisis ton format : chacun avance à son rythme.</p>
                </div>
                <a href="{{ route('textes.show', 'reglement') }}" target="_blank" rel="noopener" class="rfl-btn-secondaire px-5 py-2.5">Lire le règlement (PDF)</a>
            </div>

            <div class="rounded-[28px] bg-white px-2 py-2 ring-1 ring-bordure">
                <table class="w-full text-left max-md:block">
                    <thead class="text-sm text-gris-texte max-md:hidden">
                        <tr>
                            <th class="px-6 pt-4 pb-3 font-normal">Épreuve</th>
                            <th class="px-6 pt-4 pb-3 font-normal">Départ</th>
                            <th class="px-6 pt-4 pb-3 font-normal">Public</th>
                            <th class="px-6 pt-4 pb-3 font-normal">Tarif</th>
                            <th class="px-6 pt-4 pb-3 font-normal">Places</th>
                            <th class="px-6 pt-4 pb-3"><span class="sr-only">Action</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-bordure md:border-t md:border-bordure max-md:block">
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
                            <tr @class(['opacity-50' => $complet, 'max-md:grid max-md:grid-cols-2 max-md:gap-x-4 max-md:gap-y-1 max-md:px-4 max-md:py-5'])>
                                <td class="px-6 py-4 max-md:col-span-2 max-md:p-0">
                                    <p class="font-display text-lg text-vert">{{ $course->nom }}</p>
                                    @if ($course->distance)<p class="text-gris-texte">{{ rtrim(rtrim(number_format((float) $course->distance, 2, ',', ''), '0'), ',') }} km</p>@endif
                                </td>
                                <td class="px-6 py-4 text-vert-sombre max-md:p-0"><span class="text-gris-texte md:hidden">Départ </span>{{ \Illuminate\Support\Carbon::parse($course->heure_depart)->format('G\hi') }}</td>
                                <td class="px-6 py-4 text-vert-sombre max-md:p-0">{{ $public }}</td>
                                <td class="px-6 py-4 font-bold max-md:p-0 {{ $complet ? 'text-vert-sombre' : 'text-vert' }}">{{ $prix !== null ? number_format((float) $prix, fmod((float) $prix, 1.0) == 0.0 ? 0 : 2, ',', ' ').' €' : '–' }}</td>
                                <td class="px-6 py-4 text-vert-sombre max-md:p-0">
                                    @if ($complet)
                                        Complet
                                    @elseif ($restantes === null)
                                        Sans limite
                                    @elseif ($restantes <= 10 || $restantes / max($course->quota, 1) < 0.2)
                                        <span class="rfl-surligne bg-moutarde/25 font-bold text-vert-sombre">Plus que {{ $restantes }} {{ $restantes > 1 ? 'places' : 'place' }} !</span>
                                    @else
                                        {{ $restantes }} places
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right max-md:col-span-2 max-md:p-0 max-md:pt-3 max-md:text-left">
                                    @if ($complet)
                                        <span class="inline-block rounded-full bg-bordure px-5 py-2 font-display text-sm text-gris-texte">Complet</span>
                                    @elseif ($ouvert)
                                        <a href="{{ route('inscription.tunnel') }}" class="rfl-btn px-5 py-2 text-sm">Choisir</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="max-md:block"><td colspan="6" class="max-md:block px-6 py-10 text-center text-gris-texte">Les épreuves seront bientôt annoncées.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <p class="mt-5 text-gris-texte">L'âge pris en compte est celui du participant le jour de l'événement.</p>
        </div>
    </section>

    <section id="cause" class="bg-white py-16 sm:py-24">
        <div class="mx-auto grid max-w-6xl gap-14 px-5 sm:px-8 md:grid-cols-2 md:items-center">
            <div class="mx-auto w-full max-w-[260px] sm:max-w-[380px]">
                <x-charte.cercle disque="bg-vert-clair" arc="border-moutarde">
                    <span class="px-10 text-center text-vert">Photo de l'édition précédente</span>
                </x-charte.cercle>
            </div>

            <div>
                <h2 class="rfl-ruban text-2xl">Pourquoi on court</h2>
                <p class="mt-6 text-lg leading-relaxed text-vert-sombre">Effervescence Jeunes est une association engagée pour la santé mentale des jeunes. Elle sensibilise, prévient et agit concrètement en créant du lien, de l'écoute et de l'engagement.</p>

                <ul class="mt-8 grid gap-5 sm:grid-cols-3">
                    <li class="flex items-center gap-3 sm:flex-col sm:items-start">
                        <img src="/images/pictos/ecoute.png" alt="" class="h-12 w-12">
                        <span class="font-display text-vert">Sensibiliser</span>
                    </li>
                    <li class="flex items-center gap-3 sm:flex-col sm:items-start">
                        <img src="/images/pictos/protection.png" alt="" class="h-12 w-12">
                        <span class="font-display text-vert">Prévenir</span>
                    </li>
                    <li class="flex items-center gap-3 sm:flex-col sm:items-start">
                        <img src="/images/pictos/solidarite.png" alt="" class="h-12 w-12">
                        <span class="font-display text-vert">Créer du lien</span>
                    </li>
                </ul>

                <div class="mt-10 flex flex-wrap items-center gap-x-6 gap-y-4">
                    <a href="#" class="rfl-btn">Faire un don</a>
                    <a href="https://effervescence-jeunes.fr/" class="rfl-lien">Découvrir l'association</a>
                </div>

                <img src="/images/logo-effervescence-jeunes.png" alt="Effervescence Jeunes" class="mt-12 h-20 w-auto">
            </div>
        </div>
    </section>

    <footer id="contact" class="relative bg-vert-sombre text-white/85">
        <x-charte.colline class="absolute inset-x-0 bottom-full h-10 text-vert-sombre sm:h-16" />

        <div class="mx-auto max-w-6xl px-5 pt-12 pb-10 sm:px-8">
            <div class="grid gap-10 sm:grid-cols-2 md:grid-cols-[1.4fr_1fr_1fr_1.2fr]">
                <div>
                    <p class="font-display text-2xl text-white">Run for Lorène</p>
                    <p class="mt-3 max-w-xs leading-relaxed">Un événement Effervescence Jeunes, ensemble pour la santé mentale des jeunes.</p>
                </div>
                <div>
                    <p class="mb-3 font-display text-moutarde">L'événement</p>
                    <ul class="space-y-2">
                        <li><a href="#epreuves" class="hover:text-white hover:underline">Les épreuves</a></li>
                        <li id="reglement"><a href="{{ route('textes.show', 'reglement') }}" target="_blank" rel="noopener" class="hover:text-white hover:underline">Règlement</a></li>
                        <li><a href="#" class="hover:text-white hover:underline">Questions fréquentes</a></li>
                    </ul>
                </div>
                <div>
                    <p class="mb-3 font-display text-moutarde">Participant</p>
                    <ul class="space-y-2">
                        <li><a href="{{ route('espace') }}" class="hover:text-white hover:underline">Mon espace</a></li>
                        <li><a href="#cause" class="hover:text-white hover:underline">Faire un don</a></li>
                    </ul>
                </div>
                <div>
                    <p class="mb-3 font-display text-moutarde">Nous écrire</p>
                    <ul class="space-y-2">
                        <li><a href="mailto:contact@effervescence-jeunes.fr" class="hover:text-white hover:underline">contact@effervescence-jeunes.fr</a></li>
                        <li><a href="https://effervescence-jeunes.fr/" class="hover:text-white hover:underline">effervescence-jeunes.fr</a></li>
                    </ul>
                </div>
            </div>

            <div class="mt-12 flex flex-wrap items-center gap-x-6 gap-y-3 border-t border-white/15 pt-6 text-sm text-white/70">
                <span>N° RNA W442030950</span>
                <a href="#" class="hover:text-white">Mentions légales</a>
                <a href="#" class="hover:text-white">CGV</a>
                <a href="#" class="hover:text-white">Confidentialité</a>
                <a href="#" class="hover:text-white">Cookies</a>
                <a href="https://effervescence-jeunes.fr/" class="ml-auto p-1" title="Effervescence Jeunes">
                    <img src="/images/pastille-ej.png" alt="Effervescence Jeunes" class="h-12 w-12">
                </a>
            </div>
        </div>
    </footer>

</body>
</html>
