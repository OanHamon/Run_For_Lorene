#!/bin/sh
# Démarrage du conteneur : migrations, caches, worker de file d'attente, planificateur, puis Apache.
set -e
cd /var/www/html

# Render impose le port d'écoute via $PORT.
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Clé générée par Render (base64 brut) : Laravel attend le préfixe « base64: ».
case "${APP_KEY}" in
    base64:*|'') ;;
    *) export APP_KEY="base64:${APP_KEY}" ;;
esac

# URL publique fournie par Render si APP_URL n'est pas définie.
if [ -z "${APP_URL}" ] && [ -n "${RENDER_EXTERNAL_URL}" ]; then
    export APP_URL="${RENDER_EXTERNAL_URL}"
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

php artisan migrate --force

# Données de démonstration (RFL3, compte admin) : seulement sur demande et si la base est vide.
if [ "${RFL_SEED:-false}" = "true" ]; then
    if php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); exit(App\Models\User::count() === 0 ? 0 : 1);'; then
        echo "Base vide : lancement du seeder."
        php artisan db:seed --force
    else
        echo "Base déjà peuplée : seeder ignoré."
    fi
fi

chown -R www-data:www-data storage bootstrap/cache

# Emails (Brevo) et tâches planifiées, dans le même conteneur (pas de worker gratuit chez Render).
if [ "${RFL_WORKER:-true}" = "true" ]; then
    (while true; do su -s /bin/sh www-data -c "php artisan queue:work --tries=3 --max-time=3600 --sleep=3"; sleep 5; done) &
    (su -s /bin/sh www-data -c "php artisan schedule:work") &
fi

exec apache2-foreground
