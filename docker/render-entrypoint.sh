#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

if [[ -n "${RENDER_EXTERNAL_URL:-}" ]]; then
  export APP_URL="${RENDER_EXTERNAL_URL}"
fi

mkdir -p bootstrap/cache
mkdir -p storage/framework/{cache/data,sessions,views}
mkdir -p storage/logs
chmod -R 775 storage bootstrap/cache

php artisan optimize:clear
php artisan package:discover --ansi --force

if [[ "${APP_ENV:-production}" == "production" ]]; then
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
fi

php artisan storage:link --force || true

if [[ "${MIGRATE_ON_DEPLOY:-false}" == "true" ]]; then
  php artisan migrate --force
fi

if [[ "${SEED_ON_DEPLOY:-false}" == "true" ]]; then
  php artisan db:seed --force --class=DatabaseSeeder
fi

exec "$@"
