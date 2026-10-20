<?php

return [
    /*
    | Passerelle de paiement : "stripe" (Stripe Checkout) ou "simulation".
    | La simulation remplace Stripe par une page locale (réussite, échec, abandon) :
    | elle n'est utilisable que hors production, pour tester le parcours sans clé Stripe.
    | Par défaut : Stripe si une clé secrète est configurée, sinon simulation.
    */
    'passerelle' => env('PAIEMENT_PASSERELLE', env('STRIPE_SECRET') ? 'stripe' : 'simulation'),

    // Durée annoncée de la réservation d'une place pendant le paiement (spec : 30 minutes).
    'reservation_minutes' => 30,

    // Durée de vie de la session Stripe Checkout : Stripe impose au moins 30 minutes, d'où une
    // minute de plus. La place reste tenue tant que la session peut encore être payée, plus
    // une minute de marge pour l'arrivée du webhook (voir Course::placesPrises).
    'session_minutes' => 31,
];
