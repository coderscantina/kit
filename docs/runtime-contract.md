# Runtime contract

## One image, four processes

`Dockerfile` builds a single image; `docker/etc/supervisord.conf` runs four
things in it.

| Process     | Port | Job                                                       |
| ----------- | ---- | --------------------------------------------------------- |
| `web`       | 8000 | FrankenPHP + Octane worker mode                           |
| `horizon`   |      | queues: `default` and `reactive`, each its own supervisor |
| `reverb`    | 8001 | the websocket server                                      |
| `scheduler` |      | `schedule:work`                                           |

Services: MariaDB 11 or MySQL 8, Redis 7.

Rules the image keeps, each for a reason:

- It runs as an unprivileged `app` user. Application code is root-owned and
  read-only to it, so a compromised worker cannot rewrite the PHP the next
  request executes. `storage/` and `bootstrap/cache` are the only writable
  paths in `/app`.
- The base image's `setcap` on the frankenphp binary is removed. Nothing binds
  a privileged port.
- `install-php-extensions` is deleted from the final layer. Left in, it is a
  download-and-compile primitive for anyone who lands a shell.
- `vendor/` and `public/build` are built on the CI runner and copied in, so the
  only architecture-dependent work is apk plus the extension compile.
- `APP_VERSION` is a build arg baked into the image. Compose must never
  override it: the entrypoint compares it against the version recorded on the
  storage volume, and that comparison is what triggers upgrade migrations.
- `bootstrap/cache/*.php` is cleared. A config cache built on another machine
  ships that machine's resolved `.env`.

## Boot sequence

`docker/entrypoint.sh`:

1. Export `OCTANE_HOST` (supervisord expands it at parse time and fails hard on
   a missing variable) and `OCTANE_WORKERS`.
2. `APP_KEY` from the environment, or generate one and persist it on the
   storage volume. Containers boot in parallel, so the write is guarded by an
   atomic `mkdir` lock, reclaimed after 60 s if the holder died, and the file is
   written via tmp + `mv` so it never appears half-written.
3. `php artisan config:validate`, which stops a half-configured deployment at
   boot with a list of names instead of failing on the first request.
4. `php artisan kit:setup` when `KIT_AUTO_SETUP` is not `false`. It installs on
   first boot and migrates when `APP_VERSION` differs from the recorded one.
   Set `KIT_AUTO_SETUP=false` on every container but one so two never migrate
   the same database at the same time.
5. `exec supervisord`.

`php artisan optimize` runs `reactive:cache`, which writes
`bootstrap/cache/reactive.php` with the query and mutation name maps. Without
it every boot outside Octane walks `app/Queries` and `app/Mutations` looking
for the attributes.
`optimize:clear` runs `reactive:clear` and the next boot rediscovers them. The
image does not run `optimize` today, because Octane holds the scan for the
life of the process; run it on a host that serves without Octane.

<!--@include: ./generated/kit-setup.md-->

## Health

| Endpoint     | Auth                            | Reports                                            |
| ------------ | ------------------------------- | -------------------------------------------------- |
| `/up`        | public                          | the framework is serving; carries `X-App-Version`  |
| `/rq/health` | the `reactive.middleware` group | registry counts and worker metrics, with `version` |

`/rq/health` sits behind the same authenticated group as the rest of `/rq/*`.
The counts and the p95 describe the registry's internals, so an uptime probe
uses `/up` instead.

Its counts come from the Redis index sets, one SCARD each rather than an
EXISTS per member, so they are an upper bound between garbage collections: a
subscription whose key has expired still counts until `reactive:gc` drops it.
`worker.lock_timeout` counts recomputes that gave up waiting for the
per-computation lock; a number that is not near zero means the `reactive`
queue is contending on one hot computation.

The compose healthcheck probes `http://$(hostname):8000/up`, not loopback: a
loopback probe passes on a stack whose published port reaches nothing, so the
container reports healthy while nobody can connect to it. For the same reason
`OCTANE_HOST` is `0.0.0.0` inside the container while `APP_BIND` (default
`127.0.0.1`) decides host-side exposure.

## Local stack

```sh
bin/dev up          # rebuild and start, waiting for health
bin/dev logs app
bin/dev artisan kit:doctor
bin/dev down --wipe # also drops the volumes
```

`docker-compose.yml` refuses to boot without `DB_ROOT_PASSWORD`; `bin/dev`
generates one into `.env` on first run. The database name and user are fixed in
the compose file rather than read from `.env`, because the application and
compose share one `.env` and a host-side `DB_DATABASE` must not reach the
container.

## Production

`deploy/compose.prod.yml` is the same image against the client's MariaDB and
Redis, with `APP_ENV=production` and every secret required through `:?`.
`deploy/receiver.sh` is the reference webhook receiver: verify the
`X-Kit-Signature` HMAC, `compose pull`, `up -d --wait`, `migrate --force`,
`horizon:terminate`, `reverb:restart`, then POST the deployment status back.

## When something is wrong

```sh
php artisan kit:doctor
```

<!--@include: ./generated/kit-doctor.md-->

Reachability problems (Redis, Horizon, Reverb) are warnings, because a laptop
with services stopped is not a broken repository. Everything the repository
controls — an unregistered query name, an ambient binding missing from the
Octane flush list, an `en`/`de` key mismatch — is a failure. `--strict` promotes the warnings, which is what a deployed host wants.

A failing check and what it usually means:

- **redis unreachable** — the registry is down, so nothing pushes. Subscriptions
  fall back to a 30 s poll on the client. Restart Redis first.
- **no master supervisor** — Horizon is not running. Small writes still push
  from their own request, but large fan-out and bulk invalidations queue up and
  never land. Check the `horizon` program in supervisord.
- **reactive queue: no worker took the probe** — the doctor put a job on the
  `reactive` queue and nothing ran it within five seconds. Same cause as above,
  or a worker consuming another queue name.
- **reactive queue: started before the last change** — a worker answered, but
  its process is older than the newest file the layer loads, so it runs stale
  code. That worker once resolved a new query to "deleted" and revoked every
  subscriber. Restart it (`horizon:terminate`, or the `queue:work` process).
- **client query names not registered** — the SPA calls a name the server does
  not have. Almost always a missing `php artisan types:generate` plus a
  hand-written string.
- **octane flush list** — an ambient binding leaks into the next request the
  worker serves. Add it to `config/kit.php` `ambient_bindings`.

<!--@include: ./generated/config-validate.md-->
