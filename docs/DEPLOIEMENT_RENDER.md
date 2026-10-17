# Déploiement sur Render (préprod / démo)

L'app tourne dans un conteneur Docker (`Dockerfile`, `docker/start.sh`), décrit par le blueprint `render.yaml`.
Contrairement à un site statique, elle a besoin d'une base de données : Render n'ayant pas de MySQL, la base
est un MySQL gratuit chez Aiven. La prod reste prévue chez OVH.

Au démarrage, le conteneur : complète `APP_KEY` (générée par Render) et `APP_URL` (URL Render), met en cache
config/routes/vues, lance `migrate --force`, lance le seeder si `RFL_SEED=true` et que la base est vide,
démarre `queue:work` et `schedule:work`, puis Apache.

Pour la démo, les emails sont écrits dans les logs Render (`MAIL_MAILER=log`) et Stripe est facultatif.

## 1. Base MySQL (Aiven)

1. Créer un compte sur https://aiven.io, puis **Create service → MySQL → Free plan** (région Europe).
2. Dans l'onglet **Overview** du service, relever : Host, Port, User (`avnadmin`), Password, Database (`defaultdb`).
3. Copier le **CA certificate** (`ca.pem`).

## 2. Render

1. https://dashboard.render.com → **New → Blueprint** → dépôt `OanHamon/Run_For_Lorene`,
   branche `deploiement-render` (Render lit `render.yaml`).
2. Remplir `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (étape 1).
   Les variables Stripe peuvent rester vides.
3. Service **run-for-lorene → Environment → Secret Files → Add** : nom `ca.pem`, contenu = le certificat Aiven.
   (Si le premier déploiement est parti avant, il échoue : relancer avec **Manual Deploy**.)
4. Pour peupler la base la première fois : `RFL_SEED=true` → **Manual Deploy**. Une fois en ligne, remettre `false`.
   Le seeder crée `admin@runforlorene.fr` / `password` : **changer ce mot de passe immédiatement**.
5. Vérifier : `https://<app>.onrender.com/up` répond.

## Optionnel : vrais emails (Brevo)

Dans Brevo : **Paramètres → SMTP & API → SMTP** (login + clé SMTP), et vérifier l'adresse d'envoi.
Dans Render, Environment : `MAIL_MAILER=smtp`, `MAIL_HOST=smtp-relay.brevo.com`, `MAIL_PORT=587`,
`MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`.

## Optionnel : paiement Stripe (mode test)

1. Renseigner `STRIPE_KEY` (`pk_test_...`) et `STRIPE_SECRET` (`sk_test_...`) dans Render.
2. Dashboard Stripe (mode test) → **Développeurs → Webhooks → Ajouter un endpoint** :
   `https://<app>.onrender.com/stripe/webhook`, événements `checkout.session.completed`,
   `checkout.session.async_payment_succeeded`, `checkout.session.expired`,
   `checkout.session.async_payment_failed`, `charge.refunded`.
3. Copier le secret `whsec_...` dans `STRIPE_WEBHOOK_SECRET`.

## Limites de l'offre gratuite

- **Disque éphémère** : les PDF déposés (textes légaux, documents) disparaissent à chaque redéploiement.
  Pour les garder : Persistent Disk Render (payant) monté sur `/var/www/html/storage/app`, ou stockage S3.
- **Mise en veille** après 15 min sans visite : premier chargement ~30 s ; le worker d'emails dort aussi.
- Chaque push sur la branche déployée relance un build (désactivable : *Auto-Deploy → Off*).
