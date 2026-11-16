{{-- Pied de page du site public (accueil, page bénévoles). --}}
<footer id="contact" class="relative bg-vert-sombre text-white/85">
    <x-charte.colline class="absolute inset-x-0 bottom-full h-10 text-vert-sombre sm:h-16" />

    <div class="mx-auto max-w-6xl px-5 pt-12 pb-10 sm:px-8">
        <div class="grid gap-10 sm:grid-cols-2 md:grid-cols-[1.4fr_1fr_1fr_1.2fr]">
            <div>
                <p class="font-display text-2xl text-white">Run For Lorène</p>
                <p class="mt-3 max-w-xs leading-relaxed">Un événement Effervescence Jeunes, ensemble pour la santé mentale des jeunes.</p>
            </div>
            <div>
                <p class="mb-3 font-display text-moutarde">L'événement</p>
                <ul class="space-y-2">
                    <li><a href="{{ route('home') }}#epreuves" class="hover:text-white hover:underline">Les épreuves</a></li>
                    <li id="reglement"><a href="{{ route('textes.show', 'reglement') }}" target="_blank" rel="noopener" class="hover:text-white hover:underline">Règlement</a></li>
                </ul>
            </div>
            <div>
                <p class="mb-3 font-display text-moutarde">Participant</p>
                <ul class="space-y-2">
                    <li><a href="{{ route('espace') }}" class="hover:text-white hover:underline">Mon espace</a></li>
                    <li><a href="{{ route('benevoles') }}" class="hover:text-white hover:underline">Devenir bénévole</a></li>
                    <li><a href="{{ route('home') }}#cause" class="hover:text-white hover:underline">Faire un don</a></li>
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
            <a href="{{ route('pages.show', 'mentions-legales') }}" class="hover:text-white">Mentions légales</a>
            <a href="{{ route('pages.show', 'cgv') }}" class="hover:text-white">CGV</a>
            <a href="{{ route('textes.show', 'rgpd') }}" target="_blank" rel="noopener" class="hover:text-white">Confidentialité</a>
            <a href="{{ route('pages.show', 'cookies') }}" class="hover:text-white">Cookies</a>
            <a href="https://effervescence-jeunes.fr/" class="ml-auto p-1" title="Effervescence Jeunes">
                <img src="/images/pastille-ej.png" alt="Effervescence Jeunes" class="h-12 w-12">
            </a>
        </div>
    </div>
</footer>
