#!/bin/sh
set -e

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app

php artisan app:install

APP_KEY="$(cat storage/app.key)"
export APP_KEY

chown -R www-data:www-data storage bootstrap/cache

exec apache2-foreground
