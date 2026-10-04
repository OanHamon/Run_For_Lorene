# Run For Lorène : plateforme d'inscription

Application Laravel qui remplace TimePulse pour l'inscription aux courses Run For Lorène (association Effervescence Jeunes, Nantes). Édition visée : RFL3, samedi 3 avril 2027.

## Sources de vérité

- `docs/SPEC.md` : spécifications V1 complètes (périmètre, modèle de données, règles métier, écrans, paiement, découpage en lots). À lire en entier avant de commencer un lot.
- `docs/Maquette_flow_user_v1.pdf` : maquettes Figma exportées. À consulter avant de coder un écran ; les numéros d'écran (01 à 11, 09b) sont ceux du spec.
- En cas de contradiction : le spec l'emporte sur les maquettes.

## Stack

- Laravel 13, PHP 8.3+, MySQL (InnoDB, `utf8mb4_unicode_ci`), base locale `rfl`.
- Front : Blade, JS léger pour les panneaux du tunnel. Pas de SPA.
- Paiement : Stripe Checkout (mode test en local) et webhooks.
- Emails : Brevo, envoyés via les files d'attente Laravel.
- Environnement : WSL (Ubuntu), projet dans `/var/www/html/RFL`. Hébergement de production chez OVH (offre à confirmer).

## Commandes

```bash
composer run dev          # serveur, queue, logs et Vite
php artisan migrate:fresh --seed
php artisan test
```

## Façon de travailler

1. Avancer lot par lot, dans l'ordre de la section « Découpage en lots » du spec.
2. Avant chaque lot : lire le spec, proposer un plan court (fichiers, migrations, écrans), attendre la validation avant d'écrire le code.
3. Fin de lot : vérifier le critère « Terminé quand » du spec, lancer les tests, faire un commit clair par lot.
4. Toute règle métier absente du spec : poser la question, ne jamais l'inventer.
5. Avant le lot 1, vérifier ce qui est déjà installé (starter kit ou Laravel nu) et le signaler.

## Conventions

- Interface en français, tutoiement côté participant.
- Tables au pluriel en snake_case (convention Eloquent), colonnes selon le MLD du spec.
- Clés primaires en `increments()` ou `smallIncrements()` selon le spec, clés étrangères du même type.
- Montants en `DECIMAL(8,2)`, conversion en centimes uniquement à l'appel Stripe.
- `inscriptions.id_evenement` est une dénormalisation voulue : contraintes UNIQUE (participant, événement) et (dossard, événement).
- Réponses au questionnaire de santé chiffrées (cast `encrypted`), jamais exposées sans droit d'accès.
- Documents et consentements en soft delete, purge planifiée 12 mois après l'événement.
- Couleurs et typos : tokens de la section « Direction artistique » du spec (vert canard #00695F, moutarde #D6AD2B, Poppins et Inter).

## Tests

- Pest ou PHPUnit, écrits en même temps que le code.
- Priorité : statuts d'inscription, attribution des dossards, unicité, règles d'âge, webhooks Stripe (idempotence).

## Sécurité

- Aucune clé (Stripe, Brevo) dans le code : `.env` uniquement, `.env.example` tenu à jour.
- Fichiers privés servis par un contrôleur qui vérifie les droits.
- Validation serveur de chaque champ, protection CSRF.
