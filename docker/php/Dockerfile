# syntax=docker/dockerfile:1

###############################################################################
# Stage 1 - base
# Shared foundation: the PHP runtime plus every extension the app needs.
###############################################################################
FROM php:8.4-fpm-alpine AS base

# install-php-extensions resolves each extension's build dependencies and
# removes them afterwards.
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
        pdo_mysql \
        bcmath \
        intl \
        zip \
        opcache

# phpredis is built from its own source release rather than installed through
# PECL, because pecl.php.net no longer serves it: an installer that falls back
# to PECL fails with "No releases available for package pecl.php.net/redis".
# Pinned to an exact tag so the build stays reproducible.
ARG PHPREDIS_VERSION=6.3.0
RUN set -eux; \
    apk add --no-cache --virtual .phpredis-build-deps $PHPIZE_DEPS; \
    wget -qO /tmp/phpredis.tar.gz "https://github.com/phpredis/phpredis/archive/refs/tags/${PHPREDIS_VERSION}.tar.gz"; \
    tar xzf /tmp/phpredis.tar.gz -C /tmp; \
    cd "/tmp/phpredis-${PHPREDIS_VERSION}"; \
    phpize; \
    ./configure; \
    make -j"$(nproc)"; \
    make install; \
    docker-php-ext-enable redis; \
    cd /; \
    rm -rf "/tmp/phpredis-${PHPREDIS_VERSION}" /tmp/phpredis.tar.gz; \
    apk del .phpredis-build-deps; \
    php -m | grep -qx redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Run as a non-root user whose ids match the host user, so files written
# inside the container stay editable on the host bind mount. The ids may
# already be taken in the base image (macOS gid 20 collides with "dialout"),
# so reuse whatever exists rather than failing the build.
ARG UID=1000
ARG GID=1000
RUN set -eux; \
    if getent group "${GID}" >/dev/null; then \
        group="$(getent group "${GID}" | cut -d: -f1)"; \
    else \
        addgroup -g "${GID}" www; \
        group=www; \
    fi; \
    if getent passwd "${UID}" >/dev/null; then \
        adduser -G "${group}" -s /bin/sh -D www; \
    else \
        adduser -u "${UID}" -G "${group}" -s /bin/sh -D www; \
    fi

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

###############################################################################
# Stage 2 - development
# Keeps dev dependencies and Xdebug-friendly settings. Source arrives through
# a bind mount, so no application code is copied in.
###############################################################################
FROM base AS development

ENV APP_ENV=local

COPY docker/php/php.dev.ini /usr/local/etc/php/conf.d/99-dev.ini

# pcov rather than Xdebug: it measures coverage at a fraction of the cost.
# Built from source for the same reason as phpredis above: PECL no longer
# serves it.
ARG PCOV_VERSION=v1.0.12
RUN set -eux; \
    apk add --no-cache --virtual .pcov-build-deps $PHPIZE_DEPS; \
    wget -qO /tmp/pcov.tar.gz "https://github.com/krakjoe/pcov/archive/refs/tags/${PCOV_VERSION}.tar.gz"; \
    tar xzf /tmp/pcov.tar.gz -C /tmp; \
    cd "/tmp/pcov-${PCOV_VERSION#v}"; \
    phpize; \
    ./configure --enable-pcov; \
    make -j"$(nproc)"; \
    make install; \
    docker-php-ext-enable pcov; \
    cd /; \
    rm -rf "/tmp/pcov-${PCOV_VERSION#v}" /tmp/pcov.tar.gz; \
    apk del .pcov-build-deps; \
    php -m | grep -qx pcov

USER www

###############################################################################
# Stage 3 - vendor
# Installs production dependencies in isolation so the layer is cached on
# composer.json/composer.lock alone and not invalidated by source edits.
###############################################################################
FROM base AS vendor

COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

###############################################################################
# Stage 4 - production
# Self-contained image: application code plus a pre-built optimised autoloader.
###############################################################################
FROM base AS production

ENV APP_ENV=production \
    APP_DEBUG=false

COPY docker/php/php.prod.ini /usr/local/etc/php/conf.d/99-prod.ini

COPY --from=vendor /var/www/html/vendor ./vendor
COPY --chown=www:www . .

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-interaction \
    && chown -R www:www storage bootstrap/cache

USER www
