{{-- Mode beta (RFL_BETA=true) : démo interne, aucun vrai paiement (Stripe en mode test). Les consignes de test
     (carte 4242…) sont données en interne, pas sur le site. Lien vers le questionnaire de feedback s'il est réglé. --}}
@if (config('rfl.beta'))
    <div class="bg-moutarde px-4 py-2 text-center text-sm text-vert-sombre" role="status" data-test="bandeau-beta">
        <strong>Version démo</strong>
        @if (config('rfl.beta_feedback'))
            <a href="{{ config('rfl.beta_feedback') }}" target="_blank" rel="noopener" class="ml-1 font-bold underline underline-offset-2">Donner ton avis</a>
        @endif
    </div>
@endif
