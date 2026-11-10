# RSYNC — container for Render.com
# Stage 1 builds the CSS/JS with Vite; stage 2 is PHP 8.5 + Apache running Laravel.

# ---------- 1. Front-end assets ----------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---------- 2. PHP + Apache ----------
FROM php:8.5-apache

# PostgreSQL driver (Supabase) + zip/unzip for Composer.
RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev libzip-dev unzip git \
 && docker-php-ext-install pdo_pgsql zip \
 && rm -rf /var/lib/apt/lists/*

# Production PHP settings: 5 MB photos (same as the app's limit), faster code (opcache).
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && { \
      echo 'upload_max_filesize=5M'; \
      echo 'post_max_size=45M'; \
      echo 'memory_limit=256M'; \
      echo 'expose_php=Off'; \
      echo 'opcache.enable=1'; \
      echo 'opcache.validate_timestamps=0'; \
    } > "$PHP_INI_DIR/conf.d/rsync.ini"

# Apache serves Laravel's public/ folder; .htaccess (pretty URLs) needs mod_rewrite.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && a2enmod rewrite headers \
 && echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
 && a2enconf servername

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# PHP packages first (cached between builds when composer files don't change).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

# The app itself, then the built CSS/JS from stage 1.
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev \
 && php artisan package:discover --ansi \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

COPY docker/start.sh /usr/local/bin/start.sh
# Remove Windows line endings in case the file was saved on Windows, then make it runnable.
RUN sed -i 's/\r$//' /usr/local/bin/start.sh && chmod +x /usr/local/bin/start.sh

EXPOSE 10000
CMD ["/usr/local/bin/start.sh"]