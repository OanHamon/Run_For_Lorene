#!/bin/sh
# Démarrage du conteneur de démo : MariaDB embarquée, base recréée avec les données fictives,
# worker de file d'attente et planificateur, puis Apache.
# Les données repartent de zéro à chaque redémarrage (déploiement ou réveil après mise en veille).
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

# MariaDB embarquée
mkdir -p /run/mysqld && chown mysql:mysql /run/mysqld
if [ ! -d /var/lib/mysql/mysql ]; then
    mariadb-install-db --user=mysql --datadir=/var/lib/mysql > /dev/null
fi
mariadbd --user=mysql &
for i in $(seq 1 30); do
    mariadb-admin ping --silent 2> /dev/null && break
    sleep 1
done
mariadb -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USERNAME}'@'%' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_DATABASE}\`.* TO '${DB_USERNAME}'@'%';
FLUSH PRIVILEGES;
SQL

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

php artisan migrate:fresh --seed --force

chown -R www-data:www-data storage bootstrap/cache

# Emails et tâches planifiées, dans le même conteneur.
(while true; do su -s /bin/sh www-data -c "php artisan queue:work --tries=3 --max-time=3600 --sleep=3"; sleep 5; done) &
(su -s /bin/sh www-data -c "php artisan schedule:work") &

exec apache2-foreground
