#!/bin/sh
set -e

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app
chown -R www-data:www-data storage bootstrap/cache

php artisan app:install

exec apache2-foreground
