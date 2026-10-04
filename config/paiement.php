<?php

return [
    /*
    | Passerelle de paiement : "stripe" (Stripe Checkout) ou "simulation".
    | La simulation remplace Stripe par une page locale (réussite, échec, abandon) :
    | elle n'est utilisable que hors production, pour tester le parcours sans clé Stripe.
    | Par défaut : Stripe si une clé secrète est configurée, sinon simulation.
    */
    'passerelle' => env('PAIEMENT_PASSERELLE', env('STRIPE_SECRET') ? 'stripe' : 'simulation'),

    // Durée pendant laquelle une place reste réservée pendant le paiement (spec : 30 minutes).
    // C'est aussi la durée de vie de la session Stripe Checkout (minimum imposé par Stripe : 30 min).
    'reservation_minutes' => 30,
];
