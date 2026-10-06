#!/bin/sh
set -eu
# Artisan-команды можно запускать отдельно, до первого старта веб-сервера.
if [ "$1" != "apache2-foreground" ]; then exec "$@"; fi
: "${APP_KEY:?Set APP_KEY in .env before starting the application}"
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache
php artisan migrate --force
php artisan hspm:admin "${ADMIN_USERNAME:-admin}" --create --no-interaction
php artisan config:cache
php artisan route:cache
php artisan view:cache
exec "$@"
