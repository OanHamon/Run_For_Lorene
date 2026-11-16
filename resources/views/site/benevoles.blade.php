@php
    use App\Support\Benevolat;

    // Bouton principal selon où en est la personne.
    $action = match (true) {
        $candidature?->envoyee() => ['Voir ma candidature', route('espace.benevole')],
        $candidature !== null && $ouvert => ['Reprendre ma candidature', route('benevoles.candidature')],
        $ouvert => ['Je propose mon aide', route('benevoles.candidature')],
        default => null,
    };
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.head', ['title' => 'Devenir bénévole · Run For Lorène'])
</head>
<body class="bg-white font-sans text-vert-sombre antialiased">
    @include('partials.bandeau-beta')

    <header class="bg-white">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-6 px-5 py-4 sm:px-8">
            <a href="{{ route('home') }}" class="shrink-0 p-1">
                <img src="/images/logo-run-for-lorene.svg" alt="Run For Lorène, retour à l'accueil" class="h-16 w-auto">
            </a>
            <div class="flex items-center gap-3 sm:gap-4">
                <a href="{{ route('home') }}" class="hidden font-display text-vert underline-offset-4 hover:underline sm:inline">La course</a>
                @auth
                    <a href="{{ route('espace') }}" class="rfl-btn px-5 py-2.5">Mon espace</a>
                @else
                    <a href="{{ route('login') }}" class="font-display text-vert underline-offset-4 hover:underline">Se connecter</a>
                @endauth
            </div>
        </div>
    </header>

    <section class="relative overflow-hidden bg-vert text-white">
        <div class="mx-auto max-w-6xl px-5 pt-12 pb-24 sm:px-8 md:pt-16 md:pb-28">
            <p class="font-display text-lg text-moutarde">
                {{ $evenement ? ucfirst($evenement->date_evenement->translatedFormat('l j F Y')).' · Nantes' : 'Run For Lorène' }}
            </p>
            <h1 class="mt-4 max-w-2xl font-display text-4xl leading-[1.15] sm:text-5xl">Deviens bénévole</h1>
            <p class="mt-6 max-w-xl text-lg leading-relaxed text-white/90">
                Run For Lorène, c'est une journée pour la santé mentale des jeunes. Elle tient grâce aux bénévoles :
                tu accompagnes les équipes organisatrices et tu contribues à l'accueil et au bon déroulement de l'événement.
            </p>

            <div class="mt-9 flex flex-wrap items-center gap-4">
                @if ($action)
                    <a href="{{ $action[1] }}" class="rfl-btn-moutarde text-lg">{{ $action[0] }}</a>
                @else
                    <p class="rounded-2xl bg-white/10 px-5 py-3 text-white">Les candidatures ouvrent bientôt. Reviens nous voir !</p>
                @endif
            </div>
        </div>
        <x-charte.colline class="absolute inset-x-0 -bottom-px h-12 text-white sm:h-20" />
    </section>

    <section class="bg-white pt-6 pb-16 sm:pb-20" aria-labelledby="titre-missions">
        <div class="mx-auto max-w-6xl px-5 sm:px-8">
            <h2 id="titre-missions" class="rfl-ruban text-2xl">Les missions</h2>
            <p class="mt-4 max-w-2xl text-lg text-vert-sombre">Dans ta candidature, tu choisis les missions qui te plaisent, par ordre de préférence. L'organisation confirme ensuite ta mission selon les besoins et tes disponibilités.</p>

            <ul class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (Benevolat::MISSIONS as $mission)
                    <li class="rounded-3xl p-6 ring-1 ring-bordure">
                        <h3 class="font-display text-lg text-vert">{{ $mission['libelle'] }}</h3>
                        <p class="mt-2 leading-relaxed text-vert-sombre">{{ $mission['description'] }}</p>
                    </li>
                @endforeach
            </ul>
            <p class="mt-5 text-gris-texte">Tu peux aussi aider à l'installation la veille, à la désinstallation le soir ou au rangement le lendemain : on te le demande à part.</p>
        </div>
    </section>

    <section class="bg-fond py-16 sm:py-20" aria-labelledby="titre-deroule">
        <div class="mx-auto grid max-w-6xl gap-12 px-5 sm:px-8 md:grid-cols-2">
            <div>
                <h2 id="titre-deroule" class="rfl-ruban text-2xl">Comment ça se passe</h2>
                <ol class="mt-8 space-y-6">
                    @foreach ([
                        ['Tu candidates', 'Une dizaine de minutes : tes missions préférées, tes disponibilités et quelques infos pratiques.'],
                        ['L\'organisation étudie ta candidature', 'Tu suis où elle en est depuis ton espace. On peut te demander des précisions par email.'],
                        ['On te confirme ta participation', 'Avec ta mission. Ta candidature est un souhait : seule l\'organisation confirme la participation et l\'affectation.'],
                    ] as $i => [$titre, $texte])
                        <li class="flex gap-4">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-vert font-display text-lg text-white">{{ $i + 1 }}</span>
                            <div>
                                <p class="font-display text-lg text-vert">{{ $titre }}</p>
                                <p class="mt-1 leading-relaxed text-vert-sombre">{{ $texte }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="rounded-[28px] bg-white p-7 ring-1 ring-bordure sm:p-9">
                <img src="/images/pictos/collectif.png" alt="" class="h-14 w-14">
                <h2 class="mt-4 font-display text-2xl text-vert">Ce qui t'attend</h2>
                <ul class="mt-4 space-y-2 text-vert-sombre">
                    <li>Un tee-shirt officiel</li>
                    <li>Un badge à ton nom, avec ta photo</li>
                    <li>Un livret qui explique les missions</li>
                    <li>Des temps de présentation avant l'événement pour découvrir l'organisation et ta mission</li>
                </ul>
                <p class="mt-6 text-sm text-gris-texte">Lieu exact, horaires, profils recherchés et conditions d'engagement : informations à venir.</p>
                <p class="mt-3 text-sm text-gris-texte">
                    Tu veux aussi courir ? C'est possible, mais c'est une démarche à part :
                    <a href="{{ route('inscription.tunnel') }}" class="rfl-lien">inscris-toi à la course</a>.
                </p>
            </div>
        </div>
    </section>

    @if ($action)
        <section class="bg-white py-16 text-center">
            <h2 class="font-display text-2xl text-vert">Prêt(e) à rejoindre l'équipe ?</h2>
            <a href="{{ $action[1] }}" class="mt-6 inline-flex rfl-btn px-8 py-3.5 text-lg">{{ $action[0] }}</a>
        </section>
    @endif

    @include('partials.pied-site')
</body>
</html>
