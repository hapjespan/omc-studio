#!/bin/sh
set -e
mkdir -p .scratch
[ -f .scratch/e2e.pid ] && kill "$(cat .scratch/e2e.pid)" 2>/dev/null || true
export APP_NAME="OMC Studio" SESSION_SECURE_COOKIE="${SESSION_SECURE_COOKIE:-false}" APP_LOCALE=nl APP_FALLBACK_LOCALE=nl
export DB_CONNECTION=sqlite DB_DATABASE="$PWD/.scratch/e2e.sqlite" DB_URL= SESSION_DRIVER=database CACHE_STORE=file APP_ENV=local
rm -f "$DB_DATABASE"
php artisan app:install > .scratch/e2e-install.log 2>&1
setsid php -d display_errors=0 -d error_reporting=0 -S 127.0.0.1:8080 -t public > .scratch/e2e-serve.log 2>&1 &
echo $! > .scratch/e2e.pid
curl --silent --output /dev/null --retry 20 --retry-connrefused --retry-delay 1 http://127.0.0.1:8080/
