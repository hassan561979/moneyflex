#!/bin/sh
set -e

# Only bootstrap the application when the container actually runs the app
# (php-fpm). One-off commands such as `make test` skip straight to exec.
if [ "$1" = "php-fpm" ]; then
    # vendor/ is absent in a fresh clone.
    if [ ! -f vendor/autoload.php ]; then
        echo "[entrypoint] installing composer dependencies"
        composer install --no-interaction --prefer-dist
    fi

    if [ ! -f .env ] && [ -f .env.example ]; then
        echo "[entrypoint] .env missing, copying .env.example"
        cp .env.example .env
    fi

    mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

    # A stale cached config would pin the old, keyless configuration.
    php artisan config:clear >/dev/null 2>&1 || true

    # Generate a key whenever one is missing, so the container can never come
    # up in a state that throws MissingAppKeyException on the first request.
    if ! grep -qE '^APP_KEY=base64:.+' .env; then
        echo "[entrypoint] generating application key"
        php artisan key:generate --force --no-interaction
    fi

    if [ "${AUTO_MIGRATE:-false}" = "true" ]; then
        echo "[entrypoint] running migrations"
        php artisan migrate --force --no-interaction
    fi

    # Without this a fresh stack comes up with no accounts, and the credentials
    # quoted in the README would be rejected. Seeding backs off as soon as the
    # database holds anything, so a restart never duplicates data.
    if [ "${AUTO_SEED:-false}" = "true" ]; then
        php artisan db:seed-if-empty --no-interaction
    fi

    # Fail loudly and early rather than serving a 500 on the first request.
    php artisan about --only=environment >/dev/null
fi

exec "$@"
