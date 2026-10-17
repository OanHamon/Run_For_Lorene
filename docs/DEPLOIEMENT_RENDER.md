# Démo sur Render

Version à explorer, avec des données fictives : ce n'est pas la prod (prévue chez OVH).

Tout tient dans un conteneur Docker (`Dockerfile`, `docker/start.sh`, blueprint `render.yaml`) :
Apache + PHP 8.3 + une base MariaDB embarquée. À chaque démarrage, la base est recréée et remplie
par les seeders (RFL3 et son jeu de démo). Les modifications faites par les visiteurs disparaissent
donc au redéploiement et au réveil après mise en veille (15 min sans visite).

## Mettre en ligne

1. https://dashboard.render.com → **New → Blueprint** → dépôt `OanHamon/Run_For_Lorene`,
   branche `deploiement-render`.
2. **Apply**. Rien à saisir. Le premier build prend quelques minutes.
3. Le site est sur `https://run-for-lorene.onrender.com` (ou le nom donné par Render).

Comptes de démo (seeder) : `admin@runforlorene.fr` et `camille@exemple.fr`, mot de passe `password`.

Les emails ne partent pas : ils sont écrits dans l'onglet **Logs** du service.

## Optionnel : paiement Stripe (mode test)

1. Dans Render, Environment : `STRIPE_KEY` (`pk_test_...`), `STRIPE_SECRET` (`sk_test_...`).
2. Stripe (mode test) → **Développeurs → Webhooks → Ajouter un endpoint** :
   `https://<app>.onrender.com/stripe/webhook`, événements `checkout.session.completed`,
   `checkout.session.async_payment_succeeded`, `checkout.session.expired`,
   `checkout.session.async_payment_failed`, `charge.refunded`.
3. Copier le secret `whsec_...` dans `STRIPE_WEBHOOK_SECRET`.
