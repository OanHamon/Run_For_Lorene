<?php

return [
    /*
    | Options d'un événement (t-shirt et tailles) : masquées tant que l'étape Options du
    | tunnel (écran 07) n'est pas en service. Les données restent en base et sont dupliquées
    | avec l'événement ; seuls les écrans et les chiffres de l'admin disparaissent.
    */
    'options' => (bool) env('OPTIONS_ACTIVES', false),

    /*
    | Entrée dans le back-office : un code reçu par email est redemandé une fois ce délai
    | passé, même si l'admin est déjà connecté (cookie « se souvenir de moi » compris).
    */
    'admin_confirmation_heures' => (int) env('ADMIN_CONFIRMATION_HEURES', 8),

    /*
    | Proxy ou répartiteur de charge devant le site (hébergeur, CDN) : ses adresses, séparées
    | par des virgules, ou « * ». Sans ce réglage derrière un proxy, toutes les requêtes ont
    | l'IP du proxy : les limites par IP (connexion, codes) bloqueraient tout le monde, et
    | l'IP des consentements serait fausse. Vide : aucun proxy.
    */
    'proxies_de_confiance' => env('TRUSTED_PROXIES'),
];
