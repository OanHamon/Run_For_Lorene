# Déploiement sur Render (préprod / démo)

L'app tourne dans un conteneur Docker (`Dockerfile`, `docker/start.sh`), décrit par le blueprint `render.yaml`.
Render n'a pas de MySQL géré : la base est un MySQL gratuit chez Aiven. La prod reste prévue chez OVH.

Au démarrage, le conteneur : met en cache config/routes/vues, lance `migrate --force`, lance le seeder si
`RFL_SEED=true` et que la base est vide, démarre `queue:work` et `schedule:work`, puis Apache.

## 1. Base MySQL (Aiven)

1. Créer un compte sur https://aiven.io, puis **Create service → MySQL → Free plan** (région Europe).
2. Dans l'onglet **Overview** du service, relever : Host, Port, User (`avnadmin`), Password, Database (`defaultdb`).
3. Télécharger le **CA certificate** (`ca.pem`).

## 2. Brevo (emails)

1. **Paramètres → SMTP & API → SMTP** : relever le login SMTP et générer une clé SMTP.
2. **Expéditeurs** : vérifier l'adresse d'envoi (sera `MAIL_FROM_ADDRESS`).

## 3. Clé d'application

En local : `php artisan key:generate --show` → copier la valeur `base64:...` (sera `APP_KEY`).

## 4. Render

1. https://dashboard.render.com → **New → Blueprint** → connecter GitHub → dépôt `OanHamon/Run_For_Lorene`,
   branche `deploiement-render` (Render lit `render.yaml`).
2. Remplir les variables demandées :
   - `APP_KEY` : étape 3
   - `APP_URL` : `https://run-for-lorene.onrender.com` (adapter si Render donne un autre nom)
   - `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` : étape 1
   - `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` : étape 2
   - `STRIPE_KEY` (`pk_test_...`), `STRIPE_SECRET` (`sk_test_...`), `STRIPE_WEBHOOK_SECRET` : mettre `a-remplir`, voir étape 5
3. Service **run-for-lorene → Environment → Secret Files → Add** : nom `ca.pem`, contenu = le certificat Aiven.
   (Le premier déploiement échoue sans ce fichier : c'est normal, il suffit de relancer après l'avoir ajouté.)
4. Pour peupler la base la première fois : `RFL_SEED=true` → **Manual Deploy**. Une fois en ligne, remettre `false`.
   Le seeder crée `admin@runforlorene.fr` / `password` : **changer ce mot de passe immédiatement**.
5. Vérifier : `https://<app>.onrender.com/up` répond 200.

## 5. Webhook Stripe (mode test)

1. Dashboard Stripe (mode test) → **Développeurs → Webhooks → Ajouter un endpoint** :
   `https://<app>.onrender.com/stripe/webhook`
2. Événements : `checkout.session.completed`, `checkout.session.async_payment_succeeded`,
   `checkout.session.expired`, `checkout.session.async_payment_failed`, `charge.refunded`.
3. Copier le secret `whsec_...` dans `STRIPE_WEBHOOK_SECRET` sur Render (redéploiement automatique).

## Limites de l'offre gratuite

- **Disque éphémère** : les PDF déposés (textes légaux, documents) disparaissent à chaque redéploiement.
  Pour les garder : Persistent Disk Render (payant) monté sur `/var/www/html/storage/app`, ou stockage S3.
- **Mise en veille** après 15 min sans visite : premier chargement ~30 s ; le worker d'emails dort aussi.
- Chaque push sur la branche déployée relance un build (désactivable : *Auto-Deploy → Off*).
