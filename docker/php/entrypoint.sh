#!/bin/sh
set -e

# Only bootstrap the application when the container actually runs the app
# (php-fpm). One-off commands such as `make test` skip straight to exec.
if [ "$1" = "php-fpm" ]; then
    # vendor/ lives in a named volume in development, so it starts out empty.
    if [ ! -f vendor/autoload.php ]; then
        echo "[entrypoint] installing composer dependencies"
        composer install --no-interaction --prefer-dist
    fi

    if [ ! -f .env ] && [ -f .env.example ]; then
        echo "[entrypoint] .env missing, copying .env.example"
        cp .env.example .env
    fi

    if [ -f .env ] && ! grep -q '^APP_KEY=base64:' .env; then
        echo "[entrypoint] generating application key"
        php artisan key:generate --force --no-interaction
    fi

    mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
fi

exec "$@"
