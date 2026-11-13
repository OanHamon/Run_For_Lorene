# Checklist de mise en production — RFL3

Tout ce qui reste à faire, à vérifier ou à améliorer avant de mettre le site en ligne.
Cocher au fur et à mesure (`[x]`), une ligne à la fois.

**Repères** : beta interne mi-décembre 2026 · ouverture des inscriptions le **15 janvier 2027** ·
bénévoles en février · course le **samedi 3 avril 2027**.

**Hébergement** : OVH mutualisé **Personal** (PHP 8.5, pas de SSH, cron au mieux une fois par heure),
géré par une autre personne de l'association. Accès : utilisateur local `OAN2026`.

**En ligne** (démo interne) depuis le 13 nov. 2026 : **https://runforlorene.fr**. Site OVH
« Run for Lorène - inscriptions », dossier `rfl/public` (le projet est dans `rfl/`, à côté de `www/`
qui reste le WordPress d'effervescence-jeunes.fr). `www.runforlorene.fr` et `http://` redirigent
vers `https://runforlorene.fr` (`public/.htaccess`). Bandeau « Version démo » (`RFL_BETA=true`).

**Qui** : 🧑‍💻 Oan (code) · 🏠 gestionnaire du compte OVH · 🤝 association (contenus, décisions)

---

## ✅ Déjà fait

- [x] Failles des dépendances corrigées (`composer audit` et `npm audit` : 0 faille) — 9 nov. 2026
- [x] Billet imprimable en PDF : empreinte, QR de secours, dossard ; joint aux emails ; lisible sur papier (banc `npm run test:billet`)
- [x] Emails sans worker (`FILE_SANS_WORKER`), réveil externe (`/reveil/{clé}`, `cron-ovh.php`), API Brevo — branche `worktree-billet-pdf`, commit `0649d15`
- [x] État technique : alerte si le réveil s'arrête ou si la clé Brevo manque

---

## 🔴 Bloquant — avant la beta (mi-décembre)

### Code et dépôt 🧑‍💻

- [x] Fusionner `worktree-billet-pdf` (mode mutualisé) dans `admin`, puis `composer install` — déjà fusionné (même commit `5e4de89`), `composer install` OK, 319 tests verts — 10 nov. 2026
- [x] `docs/DECISION.md` : notes personnelles, gardé hors du dépôt (ajouté au `.gitignore`) — 10 nov. 2026
- [x] `resources/svg/track/Shooe.svg` supprimé (doublon exact de `semelles.svg`) — 10 nov. 2026
- [x] Deux branches : `master` pour développer les nouvelles fonctionnalités, `prod` pour ce qui est en ligne (on y avance `master` quand c'est prêt : `git push origin master:prod`). `admin` est fusionnée dans `master` et n'est plus utilisée — 11 nov. 2026
- [x] **Premier déploiement, à la main** — 13 nov. 2026 : paquet construit en local depuis `prod` (`git archive`, `composer install --no-dev`, `npm run build`, sans `tests/`, `docs/`, `node_modules/`), envoyé dans `rfl/` par FTP (WinSCP, fichiers cachés affichés) ; tables créées en local (`migrate:fresh` sans seeder + `QuestionnairesSeeder`, qui contient les 30 questions de santé et de décharge), exportées avec `mysqldump` et importées dans phpMyAdmin
- [ ] **Redéployer sans tout renvoyer** : en attendant l'action GitHub, envoyer par FTP seulement les fichiers modifiés (et `public/build/` après un `npm run build`) ; une nouvelle migration se joue à la main en SQL dans phpMyAdmin (`php artisan migrate --pretend` donne le SQL), en ajoutant sa ligne dans `migrations`
- [ ] **À envoyer sur le serveur** (commits de la branche `worktree-forcer-https`) : `app/Http/Controllers/Admin/ConnexionController.php` (bouton « Afficher » du mot de passe admin) et `resources/views/partials/bandeau-beta.blade.php` (bandeau « Version démo »). `public/.htaccess` (HTTPS forcé) est déjà en ligne
- [ ] **Méthode de déploiement sans SSH** (à valider) : action GitHub qui construit, à chaque mise à jour de `prod`, une branche `production-ovh` (`vendor/` sans dev + front compilé), déploiement Git d'OVH sur cette branche, `.htaccess` racine vers `public/`, `.ovhconfig`, migrations lancées par le réveil après chaque nouvelle version, guide `docs/DEPLOIEMENT.md`
- [x] **Premier compte admin** — 13 nov. 2026 : créé à la main dans la base OVH (phpMyAdmin), jamais via le seeder de démo. Une ligne dans `users` (`email` en minuscules, `nom`, `prenom`, `email_verifie_le = NOW()`, `mot_de_passe` = hash bcrypt généré en local, le mot de passe tapé à l'invite et non dans la commande : `php -r 'echo password_hash(readline("Mot de passe admin : "), PASSWORD_BCRYPT, ["cost" => 12]), PHP_EOL;'`, `created_at`/`updated_at`), puis une ligne dans `admins` (`id_user`, `niveau_acces = 3`). Connexion sur `/admin/connexion` et code reçu par email via Brevo : OK
- [x] **Bouton « Afficher » du mot de passe** sur `/admin/connexion` : ne faisait rien (Alpine absent, la page n'a pas de composant Livewire) ; corrigé par `Livewire::forceAssetInjection()` — 14 nov. 2026
- [x] **Bandeau beta** : « Version démo », sans les consignes de test (carte 4242…), données en interne — 14 nov. 2026
- [x] **Pages d'erreur en français** aux couleurs de la charte : 404, 419 (« page expirée », fréquente dans le tunnel), 500, 503 (maintenance) ; replis 4xx/5xx (401, 403, 429…). Gabarit `<x-erreur>`, vues dans `resources/views/errors/`
- [x] **Logs** : passer à des logs journaliers avec durée limitée (`LOG_STACK=daily`, `LOG_DAILY_DAYS=14`) — `.env.example` à jour
- [x] **Inscription « d'une traite »** : le paiement ne peut pas être esquivé (vérifié : seul le webhook Stripe signé valide une inscription). Un paiement non abouti remet la commande en brouillon (webhook `expired`/`async_payment_failed`, tâche `rfl:abandonner-commandes` toutes les 5 min, retour dans le tunnel). Supprimés : relance de paiement, écran « Un paiement t'attend », alerte et bouton « Payer » dans l'espace, relance admin d'un paiement en attente. Gardé : place réservée pendant la session Stripe (30 min). Relance automatique des documents en pause par défaut. Spec et parcours à jour — 10 nov. 2026
- [x] Corriger le `CLAUDE.md` : le projet est en **Laravel 12**, pas 13

### Hébergement OVH 🏠 + 🧑‍💻

- [ ] Changer le mot de passe de `OAN2026` (il est identique à celui du créateur du compte), et le créateur change le sien s'il lui sert ailleurs
- [ ] Activer la double authentification sur les deux accès si OVH le propose
- [x] Créer la base MySQL de production (Web Cloud → Hosting plans → Databases), MySQL 8.4, identifiants notés — 13 nov. 2026
- [x] Site `runforlorene.fr` (+ `www`) sur le dossier `rfl/public` (onglet « My sites », DNS automatique, sans firewall ni CDN) — 13 nov. 2026
- [x] Certificat SSL Let's Encrypt actif sur `runforlorene.fr` et `www.runforlorene.fr` (renouvelé par OVH), HTTPS forcé par `public/.htaccess` — 13 nov. 2026
- [x] Déposer `.ovhconfig` (`app.engine.version=8.5`, `environment=production`, `http.firewall=none`), dans `rfl/` et `rfl/public/` : PHP 8.5 confirmé en ligne — 13 nov. 2026
- [ ] Associer le dépôt GitHub (clé SSH de déploiement, dépôt privé) au dossier du site, branche `production-ovh`
- [x] Déposer le `.env` de production par FTP (jamais dans git) — 13 nov. 2026
- [ ] **(plus tard)** Cron OVH : `rfl/cron-ovh.php` toutes les heures, PHP 8.5, sans logs par email
- [ ] `runforlorene.org` : le rediriger vers `https://runforlorene.fr`
- [ ] Vérifier les limites de l'offre : taille max d'un envoi de fichier (`upload_max_filesize` ≥ 10 Mo pour les PDF légaux), `memory_limit` (dompdf), durée max d'exécution, taille de la base

### Services externes 🧑‍💻 + 🤝

- [x] **Brevo** : compte créé, clé API restreinte (« Emails transactionnels » seulement), expéditeur `contact@runforlorene.fr` vérifié — 13 nov. 2026
- [x] **Brevo** : domaine `runforlorene.fr` authentifié, en manuel (TXT `brevo-code`, CNAME `brevo1._domainkey` et `brevo2._domainkey`, TXT `_dmarc` en `p=none`) ; SPF OVH existant inchangé — 13 nov. 2026
- [ ] **Brevo : la clé API expire** le 13 nov. 2027 ou après 90 jours sans envoi (donc vers juillet 2027) : en recréer une pour l'édition suivante
- [ ] **Brevo** : vérifier où arrivent les emails (boîte de réception ou spam) sur Gmail, Outlook, Orange, Free
- [ ] **Brevo : vérifier le quota.** L'offre gratuite est limitée en emails par jour (environ 300 au moment de l'audit, à confirmer). Le jour de l'ouverture, avec codes de connexion, confirmations et billets, la limite peut sauter : prévoir l'offre payante au moins pour janvier
- [ ] **(plus tard) cron-job.org** : un réveil chaque minute sur `https://<domaine>/reveil/<REVEIL_CLE>`, alerte email en cas d'échec
- [ ] **Stripe** : compte de l'association vérifié, clés live
- [ ] **Stripe** : webhook de production vers `https://<domaine>/stripe/webhook` avec les événements `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`, `checkout.session.expired`, `charge.refunded`, `charge.updated` ; noter le `whsec_…`
- [ ] **Stripe** : vérifier le domaine pour Apple Pay / Google Pay
- [ ] Surveillance : un service gratuit (UptimeRobot ou équivalent) sur `https://<domaine>/up`

### `.env` de production 🧑‍💻

- [x] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://runforlorene.fr` — 13 nov. 2026
- [x] `APP_KEY` générée **une seule fois** et sauvegardée en lieu sûr : la changer **rend tous les billets invalides** (signature) et **rend illisibles les réponses de santé** (chiffrées) — 13 nov. 2026
- [x] `SESSION_SECURE_COOKIE=true`, `LOG_LEVEL=warning`, `LOG_STACK=daily`, `LOG_DAILY_DAYS=14` — cookies `secure` vérifiés en ligne
- [x] `DB_*` de la base OVH
- [x] `MAIL_MAILER=brevo`, `BREVO_API_KEY`, `MAIL_FROM_ADDRESS="contact@runforlorene.fr"`
- [x] `QUEUE_CONNECTION=database`, `FILE_SANS_WORKER=true`, `REVEIL_CLE` (64 caractères aléatoires, pas encore utilisée : réveil à configurer)
- [ ] `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` (clés de test pendant la beta)
- [ ] `RFL_BETA=true` (fait) et `RFL_BETA_FEEDBACK_URL` (vide pour l'instant) pendant la beta, puis `false`
- [x] `OTP_DANS_LES_LOGS=false`
- [ ] `TRUSTED_PROXIES` : à régler seulement si OVH place un proxy devant le site (vérifier l'IP vue par le site)
- [ ] Après la mise en ligne : ouvrir « État technique » dans l'admin, tout doit être au vert

---

## 🟠 Bloquant — avant l'ouverture du 15 janvier

### Fonctionnalités du spec 🧑‍💻

- [ ] **Reçu fiscal des dons en PDF** (spec, lot 5 ; dompdf est maintenant disponible)
- [ ] Décharge pour les mineurs : en existe-t-il une version ? (🤝)

### Sauvegardes 🧑‍💻 + 🏠

- [ ] Vérifier ce que l'offre Personal sauvegarde de la base, et à quelle fréquence
- [ ] Prévoir une sauvegarde de la base **hors OVH** (export régulier), et **tester une restauration** au moins une fois
- [ ] Sauvegarder aussi `storage/app/private` (documents déposés, PDF légaux) et le `.env`

### Contenus RFL3 🤝

- [ ] Lieu, épreuves, horaires, âges, tarifs, quotas, plages de dossards par course
- [ ] Textes légaux en PDF : règlement, CGV, politique de protection des données, décharge
- [ ] Mentions légales : responsable de publication, siège, **hébergeur OVH (adresse exacte)**
- [ ] Politique de confidentialité : citer les sous-traitants (OVH, Stripe, Brevo)
- [ ] Texte « La cause » et photos (lumineuses, personnes réelles, selon la charte)
- [ ] Options et tailles (t-shirts) si l'étape Options est activée (`OPTIONS_ACTIVES`)

### Recette (lot 7 du spec) 🧑‍💻 + 🤝

- [ ] Course fictive privée de bout en bout sur le vrai serveur : inscription, paiement Stripe test réussi / échoué / abandonné, email reçu avec le billet PDF, dossier complété après coup
- [ ] Parcours complet validé par l'association
- [ ] Revue accessibilité et mobile (petits écrans, contraste, clavier)

---

## 🔎 À vérifier sur le vrai serveur

- [ ] Le site tourne en **PHP 8.5** (le développement se fait en 8.3) : il démarre et la connexion admin marche (13 nov. 2026) ; reste à relancer la suite de tests en 8.5 ou faire la recette complète sur OVH
- [ ] Un email part dans la seconde après une inscription (file vidée après la page)
- [ ] Le réveil passe chaque minute (État technique → « Réveil du site »)
- [ ] La purge de nuit, l'abandon des commandes non payées et les envois programmés tournent (État technique → tâches planifiées)
- [ ] Les appels sortants vers Stripe et Brevo passent depuis l'hébergement
- [ ] Génération d'un billet PDF (environ 2 s) sans dépasser la mémoire ni le temps max
- [ ] **Billet imprimé réel** : imprimer en couleur et en noir et blanc, scanner l'empreinte puis le QR avec le poste de retrait sur un vrai téléphone
- [ ] Les emails arrivent en boîte de réception (Gmail, Outlook, Orange, Free), pas en spam
- [x] Le `.env`, `storage/` et le code ne s'ouvrent pas depuis le web : `/.env` → 403, `/storage/logs/laravel.log` → 404 — 13 nov. 2026
- [ ] **Tenue en charge** pour l'ouverture : simuler quelques dizaines d'inscriptions simultanées sur l'offre Personal

---

## 🟢 À améliorer (non bloquant)

- [x] Page de maintenance aux couleurs de la charte pendant un déploiement : `php artisan down --render="errors::503"` (page pré-rendue, servie sans démarrer l'application)
- [ ] Rendu PDF : réutiliser l'image de l'empreinte si plusieurs emails la demandent (gain de temps)
- [ ] Version monochrome blanche du logo Effervescence Jeunes pour les fonds foncés (à demander à Pop Com)
- [ ] Mettre à jour les écrans Figma 06, 08, 10 et 09 (questionnaire de santé, Stripe, Apple Pay)
- [ ] Audit TimePulse : champs et fonctionnalités réellement utilisés (point ouvert du spec)

---

## 📅 Après la mise en ligne

- [ ] Jour de l'ouverture : surveiller « État technique » (file, échecs d'envoi, paiements à vérifier)
- [ ] Fin de la beta : `RFL_BETA=false`, clés Stripe live, base remise à zéro des données de test
- [ ] Postes de retrait des dossards créés et testés avec les bénévoles avant le 3 avril
- [ ] Purge RGPD automatique 12 mois après l'événement : vérifier qu'elle tourne en avril 2028

---

## ➡️ Ensuite : le brief complémentaire (8 novembre 2026)

À cadrer avec l'association **après** la mise en production : inscriptions de groupes
(individuel ou payé par la structure), candidatures bénévoles (Google Forms intégré, photo
de badge), ateliers du Village Santé Mentale (cartes, créneaux, jauges, listings par
association), espace utilisateur unifié et droits par rôle.
