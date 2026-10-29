{{-- Liste factuelle des cookies posés par le site (à relire par l'association). --}}
<x-tunnel-shell :title="$titre.' · Run For Lorène'">
    <h1 class="font-display text-2xl text-vert">{{ $titre }}</h1>

    <div class="mt-4 space-y-4 text-sm leading-relaxed text-vert-sombre">
        <p>Le site n'utilise ni publicité, ni mesure d'audience, ni réseau social. Il ne dépose que les cookies nécessaires à son fonctionnement : ils ne demandent pas ton accord.</p>

        <ul class="divide-y divide-bordure rounded-2xl border border-bordure">
            <li class="px-4 py-3"><span class="font-bold">Session</span> · garde ta connexion et ton inscription en cours. Supprimé après 2 heures sans activité.</li>
            <li class="px-4 py-3"><span class="font-bold">XSRF-TOKEN</span> · protège les formulaires contre les envois frauduleux. Même durée que la session.</li>
            <li class="px-4 py-3"><span class="font-bold">Rester connecté</span> · seulement si tu as coché « Rester connecté » : évite de te reconnecter à chaque visite, jusqu'à 400 jours ou ta déconnexion.</li>
        </ul>

        <p>Le paiement se fait sur la page sécurisée de Stripe, qui dépose ses propres cookies pour la sécurité du paiement.</p>
        <p>Pour en savoir plus sur tes données : <a href="{{ route('textes.show', 'rgpd') }}" target="_blank" rel="noopener" class="rfl-lien">politique de protection des données</a>.</p>
    </div>

    <div class="mt-8">
        <a href="{{ route('home') }}" class="rfl-lien">Retour à l'événement</a>
    </div>
</x-tunnel-shell>
