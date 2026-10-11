#!/usr/bin/env sh
# Starts the builder in a container: makes an app key if none is set, prepares the
# database and the playbook library, then serves on $PORT.
set -e

if [ -z "$APP_KEY" ]; then
  APP_KEY="$(php artisan key:generate --show --no-interaction)"
  export APP_KEY
fi

if [ "$DB_CONNECTION" = "sqlite" ] && [ ! -f "$DB_DATABASE" ]; then
  touch "$DB_DATABASE"
fi

php artisan migrate --force --seed
php artisan config:cache
php artisan view:cache

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}" --no-reload
