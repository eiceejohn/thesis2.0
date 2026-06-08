#!/usr/bin/env bash
set -e

cd /var/www/html

export APP_ENV="${APP_ENV:-production}"
export APP_DEBUG="${APP_DEBUG:-false}"
export DB_CONNECTION="${DB_CONNECTION:-sqlite}"
export SESSION_DRIVER="${SESSION_DRIVER:-database}"
export CACHE_STORE="${CACHE_STORE:-database}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-database}"
export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"
export PORT="${PORT:-10000}"

if [ -z "${APP_URL:-}" ]; then
    if [ -n "${RAILWAY_PUBLIC_DOMAIN:-}" ]; then
        export APP_URL="https://${RAILWAY_PUBLIC_DOMAIN}"
    elif [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
        export APP_URL="${RENDER_EXTERNAL_URL}"
    else
        export APP_URL="http://localhost"
    fi
fi

if [ -z "${DB_DATABASE:-}" ]; then
    if [ -n "${RAILWAY_VOLUME_MOUNT_PATH:-}" ]; then
        export DB_DATABASE="${RAILWAY_VOLUME_MOUNT_PATH}/database.sqlite"
    else
        export DB_DATABASE="/var/www/html/database/database.sqlite"
    fi
fi

if [ -z "${APP_KEY:-}" ]; then
    export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
fi

mkdir -p "$(dirname "$DB_DATABASE")" storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache
touch "$DB_DATABASE"

php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan migrate --force
php artisan db:seed --force
php artisan audit:recalculate --no-interaction
php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache "$(dirname "$DB_DATABASE")"

php artisan serve --host=0.0.0.0 --port="$PORT"
