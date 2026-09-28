FROM dunglas/frankenphp:php8.5-alpine

# Everything in this image runs as this unprivileged user: supervisord itself
# and therefore octane/frankenphp, horizon, reverb and the scheduler.
ARG APP_UID=1000
ARG APP_GID=1000

RUN apk add --no-cache supervisor mysql-client \
    && install-php-extensions \
    bcmath \
    exif \
    gd \
    intl \
    pcntl \
    pdo_mysql \
    redis \
    zlib \
    && addgroup -g ${APP_GID} app \
    && adduser -D -u ${APP_UID} -G app -h /home/app -s /sbin/nologin app \
    # Octane serves on 8000 and reverb on 8001, so the binary never needs to
    # bind a privileged port. Drop the capability the base image ships with.
    && apk add --no-cache --virtual .setcap libcap \
    && { setcap -r /usr/local/bin/frankenphp || true; } \
    && apk del .setcap \
    # An extension installer left in the image is a ready-made
    # download-and-compile primitive for anyone who lands a shell in it.
    && rm -f /usr/local/bin/install-php-extensions \
    && rm -rf /var/cache/apk/*

COPY docker/php.ini /usr/local/etc/php/conf.d/docker-php-override.ini
COPY docker/etc /etc

WORKDIR /app

# Application code stays root-owned and read-only to the runtime user: a
# compromised worker cannot rewrite the PHP the next request executes.
#
# vendor/ and public/build are built on the CI runner and copied in, so the
# only architecture-dependent work here is apk plus the extension compile.
# That is what makes an emulated arm64 build bearable. Build them first:
#   composer install --no-dev --optimize-autoloader && bun run build
COPY artisan composer.json /app/
COPY bootstrap /app/bootstrap/
COPY storage /app/storage/
COPY vendor /app/vendor/
# vendor/kit/reactive is a path-repository symlink into packages/.
COPY packages /app/packages/
COPY public /app/public/
COPY resources /app/resources/
COPY config /app/config/
COPY database /app/database/
COPY routes /app/routes/
COPY app /app/app/

COPY docker/entrypoint.sh /usr/local/bin/kit-entrypoint

# storage/ and bootstrap/cache are the only writable paths in /app; /config and
# /data are frankenphp's XDG directories, where caddy keeps its state.
RUN mkdir -p \
        /app/storage/app/private \
        /app/storage/app/public \
        /app/storage/app/setup \
        /app/storage/framework/cache/data \
        /app/storage/framework/sessions \
        /app/storage/framework/views \
        /app/storage/logs \
        /app/storage/tmp \
        /app/bootstrap/cache \
        /config/caddy /data/caddy \
    # A config cache from the build machine would ship that machine's resolved
    # .env into production.
    && rm -f /app/bootstrap/cache/*.php \
    # public/ is gitignored, and octane can no longer drop the worker stub in
    # on first boot because /app is read-only to the runtime user.
    && cp vendor/laravel/octane/src/Commands/stubs/frankenphp-worker.php public/frankenphp-worker.php \
    && chmod -R go-w /app \
    && chmod 755 /usr/local/bin/kit-entrypoint \
    && chown -R app:app /app/storage /app/bootstrap/cache /config /data /home/app

VOLUME /app/storage

# Baked in, never overridden by compose: the entrypoint compares it against the
# recorded install state to decide whether an upgrade migration has to run.
ARG APP_VERSION=dev
ENV APP_VERSION=$APP_VERSION
ENV HOME=/home/app
# Tools that shell out honour TMPDIR, not php.ini's sys_temp_dir.
ENV TMPDIR=/app/storage/tmp

EXPOSE 8000 8001

USER app

ENTRYPOINT ["/usr/local/bin/kit-entrypoint"]
