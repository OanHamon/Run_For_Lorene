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

    /*
    | Beta test interne (mi-décembre 2026) : bandeau « Version de test » sur toutes les pages,
    | avec le lien vers le questionnaire de feedback. Les paiements passent par Stripe en mode
    | test (clés sk_test_…) : rien n'est débité. L'état technique signale une clé live.
    */
    'beta' => (bool) env('RFL_BETA', false),
    'beta_feedback' => env('RFL_BETA_FEEDBACK_URL'),

    /*
    | Hébergement mutualisé (OVH Personal) : pas de worker permanent ni de cron à la minute.
    | `file_sans_worker` : chaque requête qui met un email en file l'envoie juste après sa
    | réponse (App\Support\FileSansWorker). `reveil_cle` : clé secrète de l'adresse
    | /reveil/{clé}, appelée chaque minute par un service externe (cron-job.org) pour lancer
    | les tâches planifiées et vider la file (App\Services\Reveil). Vide : adresse désactivée.
    */
    'file_sans_worker' => (bool) env('FILE_SANS_WORKER', false),
    'reveil_cle' => env('REVEIL_CLE'),
];
