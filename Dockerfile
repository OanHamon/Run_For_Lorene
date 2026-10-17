# Image de déploiement (Render, ou tout hébergeur Docker).
# Démo à explorer : MariaDB embarquée, base recréée avec des données fictives à chaque démarrage.
# 1) dépendances PHP (dev incluses : le DemoSeeder utilise Faker)  2) build Vite (le CSS importe Flux depuis vendor/)
# 3) image finale Apache + PHP 8.3 + MariaDB

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY --from=vendor /app /app
RUN npm run build

FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libzip-dev unzip mariadb-server \
    && docker-php-ext-install pdo_mysql intl zip bcmath opcache pcntl \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-rfl.ini"
COPY docker/mariadb.cnf /etc/mysql/mariadb.conf.d/99-rfl.cnf

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

RUN rm -f public/hot \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private storage/app/public bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x docker/start.sh

ENV PORT=10000 \
    DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=rfl DB_USERNAME=rfl DB_PASSWORD=rfl
EXPOSE 10000

CMD ["docker/start.sh"]
