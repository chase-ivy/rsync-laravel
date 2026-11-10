#!/bin/sh
# Runs every time Render starts the app.
set -e

# Render tells the app which port to use (default 10000).
PORT="${PORT:-10000}"
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Read the settings from Render's Environment tab once, so every request is fast.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Add any NEW tables/columns. Never deletes data (only pending migrations run).
php artisan migrate --force

exec apache2-foreground