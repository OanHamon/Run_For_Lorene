{{-- Mode beta (RFL_BETA=true) : version de test, aucun vrai paiement (Stripe en mode test), lien vers le questionnaire de feedback. --}}
@if (config('rfl.beta'))
    <div class="bg-moutarde px-4 py-2 text-center text-sm text-vert-sombre" role="status" data-test="bandeau-beta">
        <strong>Version de test</strong> · aucun vrai paiement : utilise la carte 4242 4242 4242 4242, une date future et n'importe quel code.
        @if (config('rfl.beta_feedback'))
            <a href="{{ config('rfl.beta_feedback') }}" target="_blank" rel="noopener" class="ml-1 font-bold underline underline-offset-2">Donner ton avis</a>
        @endif
    </div>
@endif
