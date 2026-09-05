#!/bin/sh
set -eu

# Wrapper around supervisord. With every variable provided and KIT_AUTO_SETUP
# unset this is a strict pass-through.

# supervisord expands %(ENV_...)s at parse time and fails hard on a missing
# variable, so these always have to be set. 127.0.0.1 is the safe default;
# compose sets 0.0.0.0 so the published port is reachable.
export OCTANE_HOST="${OCTANE_HOST:-127.0.0.1}"
export OCTANE_WORKERS="${OCTANE_WORKERS:-4}"

# APP_KEY: prefer the environment; otherwise generate one and persist it on the
# storage volume, because /app is read-only for this user.
if [ -z "${APP_KEY:-}" ]; then
    KEY_FILE=/app/storage/app/setup/app.key
    mkdir -p "$(dirname "$KEY_FILE")"

    # Containers sharing this volume boot in parallel. mkdir is atomic, so
    # exactly one of them generates the key while the others wait for the
    # finished file. A generator that dies mid-run leaves a stale lock, which a
    # waiter reclaims after 60s.
    have_lock=0
    waited=0
    # -s, not -f: a failed earlier attempt must not leave an empty key file
    # that every later boot exports as APP_KEY.
    while [ ! -s "$KEY_FILE" ]; do
        if mkdir "$KEY_FILE.lock" 2>/dev/null; then
            have_lock=1
            break
        fi
        waited=$((waited + 1))
        if [ "$waited" -gt 60 ]; then
            echo "kit-entrypoint: reclaiming stale APP_KEY lock" >&2
            rm -rf "$KEY_FILE.lock"
        fi
        sleep 1
    done

    if [ "$have_lock" = 1 ]; then
        # Re-check under the lock, then write via a unique tmp file + mv so the
        # final file only ever appears complete.
        if [ ! -s "$KEY_FILE" ]; then
            php /app/artisan key:generate --show > "$KEY_FILE.tmp.$$"
            chmod 600 "$KEY_FILE.tmp.$$"
            mv "$KEY_FILE.tmp.$$" "$KEY_FILE"
        fi
        rmdir "$KEY_FILE.lock"
    fi

    APP_KEY="$(cat "$KEY_FILE")"
    export APP_KEY
fi

# Stops a half-configured deployment at boot with a list of names instead of
# failing on the first request.
php /app/artisan config:validate

# kit:setup installs on first boot and, when APP_VERSION differs from the
# version recorded on the storage volume, runs `migrate --force` for whatever
# the new image added. An ordinary restart matches the recorded version and
# costs nothing. Set KIT_AUTO_SETUP=false on every container but one so two of
# them never migrate the same database at the same time.
if [ "${KIT_AUTO_SETUP:-true}" = "true" ]; then
    php /app/artisan kit:setup
fi

exec /usr/bin/supervisord -c "${KIT_SUPERVISORD_CONF:-/etc/supervisord.conf}" -n
