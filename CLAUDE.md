# Run For Lorène : plateforme d'inscription

Application Laravel qui remplace TimePulse pour l'inscription aux courses Run For Lorène (association Effervescence Jeunes, Nantes). Édition visée : RFL3, samedi 3 avril 2027.

## Sources de vérité

- `docs/SPEC.md` : spécifications V1 complètes (périmètre, modèle de données, règles métier, écrans, paiement, découpage en lots). À lire en entier avant de commencer un lot.
- `docs/Maquette_flow_user_v1.pdf` : maquettes Figma exportées. À consulter avant de coder un écran ; les numéros d'écran (01 à 11, 09b) sont ceux du spec.
- `docs/26 02 2026 - CHARTE Identite visuelle - Effervescence jeunes.pdf` : charte graphique (agence Pop Com, 26/02/2026). Source de vérité absolue pour tout le front : couleurs, typographies, logos, formes, pictogrammes, ton. Résumé exploitable dans la section « Charte graphique » ci-dessous ; relire le PDF en cas de doute.
- Logos sources dans `docs/` (`LOGO Run for Lorene*`, `imgi_1_effervescence-jeunes.png`, `Logos_EffervescenceJeunes-03.png`) : ne jamais les référencer depuis le code, utiliser les copies web de `public/images/`.
- En cas de contradiction : la charte l'emporte sur tout pour le visuel (y compris la section « Direction artistique » du spec) ; pour le reste, le spec l'emporte sur les maquettes.

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

## Charte graphique

Esprit : « élan collectif ». Positif sans naïveté, humain, clair, accessible. Formes arrondies, angles doux, beaucoup d'espace, une idée principale par écran. En cas de doute : sobre et lisible.

### Couleurs (uniquement celles-ci)

| Charte | Hex | Usage |
| --- | --- | --- |
| Vert profond | #006B61 | Couleur principale : titres, fonds forts, boutons, éléments structurants |
| Vert doux | #5FBAAE | Aplats secondaires, pictogrammes, encadrés, contenus pédagogiques |
| Jaune moutarde | #D9B21E | Accent en touche : dates, chiffres clés, mots importants. Jamais en excès |
| Vert sombre | #23463F | Contraste : texte sur aplat ou photo, fonds très foncés |
| Vert clair | #BEECE6 | Contraste : fonds clairs, aplats doux |

- Proportions : le vert profond structure, le vert doux apporte la profondeur, la moutarde reste un accent.
- La moutarde n'est pas lisible en texte sur fond blanc (contraste AA non atteint) : la réserver aux fonds, pastilles, soulignements, ou au texte sur fond vert profond / sombre.
- Les tokens Tailwind sont dans `resources/css/app.css` (`@theme`) ; toujours passer par eux, jamais de hex en dur dans les vues.

### Typographies

- Varela Round : titres et éléments de marque (la plus proche du logo Effervescence).
- Calibri : texte courant et formulaires. Police non libre : pile `Calibri, Carlito, sans-serif` (Carlito est l'équivalent métrique libre).
- Lumios Marker (manuscrite) : disponible uniquement dans Canva, pas utilisée sur le site.
- Pas d'effets typographiques ; titres courts, textes aérés, interlignage confortable.

### Logos et images

Copies web dans `public/images/`, à utiliser telles quelles :

| Fichier | Contenu | Usage |
| --- | --- | --- |
| `logo-run-for-lorene.svg` | Logo Run for Lorène (vectoriel, issu du `.ai`) | En-têtes du site, du tunnel et de l'espace participant |
| `logo-effervescence-jeunes.png` | Logo Effervescence Jeunes complet | Mise en avant de l'association organisatrice, sur fond clair |
| `pastille-ej.png` | Déclinaison ronde « ej » | Petits formats, signature en pied de page, fond foncé |

Favicons (`public/favicon.ico`, `favicon-32.png`, `apple-touch-icon.png`) générés à partir de la pastille « ej ».

- Toujours la version officielle : jamais déformer, recolorer, ni ajouter d'effet (ombre, filtre, opacité).
- Zone de protection autour du logo : au moins la hauteur du « e » d'Effervescence. Rien ne doit empiéter dessus.
- Logo jamais centré au milieu d'un contenu ni intégré dans un paragraphe ; en signature, en bas à droite.
- Sur fond foncé : version monochrome blanche (pas encore fournie, la demander) ou pastille « ej ». Ne pas poser le logo couleur sur du vert.
- Pictogrammes : uniquement ceux de la charte (galerie p. 11), aux couleurs de la charte, un picto par idée, jamais à la place d'un message important.
- Photos : personnes réelles, moments de partage, lumineuses. Rien de sombre, anxiogène ou artificiel.

### Ton

Simple, direct, humain, inclusif, verbes d'action, phrases courtes. À éviter : culpabilisation, sensationnalisme, ton alarmiste, jargon institutionnel.

## Tests

- Pest ou PHPUnit, écrits en même temps que le code.
- Priorité : statuts d'inscription, attribution des dossards, unicité, règles d'âge, webhooks Stripe (idempotence).

## Sécurité

- Aucune clé (Stripe, Brevo) dans le code : `.env` uniquement, `.env.example` tenu à jour.
- Fichiers privés servis par un contrôleur qui vérifie les droits.
- Validation serveur de chaque champ, protection CSRF.
