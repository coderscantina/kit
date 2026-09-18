# Kit

A Laravel 13 + Vue 3 starter kit with a Convex-style data layer. The client
subscribes to server-side query functions, writes go through transactional
mutations, and a result that changes is pushed to everyone watching it, end to
end typed.

It ships the parts every client app needs before the first feature: login,
registration behind a first-account latch, password reset, email verification,
TOTP with backup codes, password confirmation, invites, roles enforced by
policies, impersonation, an app shell, `en`/`de` i18n and a streamed AI layer
on OpenRouter. It ships no example feature. Features come from the generators.

## Get it running

```sh
git clone <repo> kit && cd kit
bin/dev up
```

That builds the image, boots MariaDB and Redis, runs `kit:setup` and serves the
app on <http://localhost:8000>. Register the first account: it becomes the owner
and closes self-registration. Under ten minutes on a fresh machine, most of it
the image build.

For a host PHP setup instead of the container, with PHP, bun and a Redis on
the host (`bin/dev services` runs MariaDB and Redis in containers on 127.0.0.1
if you have neither):

```sh
composer install && bun install
bin/dev start    # serve, vite, reverb and horizon, detached
bin/dev status   # each process running or dead
bin/dev stop     # stops exactly what start started
```

`start` fills in `.env`, runs `kit:setup` and `db:seed`, and returns once
`/up` and Vite answer. Logs go to `storage/logs/dev-<name>.log`. With the
containerised MariaDB, set `DB_USERNAME=root` and `DB_PASSWORD` to
`DB_ROOT_PASSWORD`; `DB_CONNECTION=sqlite` needs no database server at all.

Locally, `db:seed` creates one verified account per role: `owner@kit.test`,
`admin@kit.test` and `member@kit.test`, all with the password `password`.
`GET /dev/login/{role}` signs in as one of them and redirects to `/`. It
exists only with `APP_ENV=local` and `APP_DEBUG=true`.

## Commands

| Command                                      | Does                                                                                                                                                     |
| -------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `bin/dev up`                                 | rebuild and start the local stack, waiting for health                                                                                                    |
| `bin/dev down --wipe`                        | stop it and drop the volumes                                                                                                                             |
| `bin/dev artisan <cmd>`                      | run artisan inside the container                                                                                                                         |
| `bin/gate [--changed]`                       | everything CI runs, locally; `--changed` scopes it to the diff against `main`                                                                            |
| `bin/release`                                | cut and push a CalVer release; `--dry-run`, `--backfill`                                                                                                 |
| `php artisan make:feature Post --fields=…`   | scaffold a feature from its columns, its `list` query and a page that renders it, wired into the registry, router, nav and messages                      |
| `php artisan make:query posts.list`          | a query, its data class and a test asserting push-on-change and 403                                                                                      |
| `php artisan make:mutation posts.create`     | a mutation, its args and data classes and a test asserting the commit, the invalidation and 403; `--versioned` for the edit path with its 409 and dialog |
| `php artisan make:ai-action posts.summarize` | a streamed AI action, its args class and a test that runs it against a faked model                                                                       |
| `php artisan make:filter Post`               | a list filter with its sort allow list and whitelist, and its test                                                                                       |
| `php artisan make:endpoint posts.import`     | a REST endpoint: controller, FormRequest, action, route, `api` resource method and test                                                                  |
| `php artisan ai:models`                      | the provider's model catalogue, with context window and price                                                                                            |
| `php artisan make:data Post/Summary`         | a laravel-data class with the TypeScript attribute                                                                                                       |
| `php artisan make:job RebuildIndex`          | a `QueuedJob` subclass                                                                                                                                   |
| `php artisan types:generate [--check]`       | regenerate `resources/js/types/generated.d.ts`                                                                                                           |
| `php artisan reactive:cache`                 | cache the query and mutation name maps; `optimize` runs it                                                                                               |
| `php artisan kit:setup`                      | idempotent first run and upgrade                                                                                                                         |
| `php artisan kit:doctor`                     | check the runtime, the registry and the message files                                                                                                    |
| `bun run docs:dev`                           | the documentation site                                                                                                                                   |

## Stack

Laravel 13 on PHP 8.5 under FrankenPHP + Octane, MariaDB 11 or MySQL 8,
Redis 7, Reverb (optional; without it the client polls), Horizon. Vue 3, TypeScript, Vite 8, TanStack Query v5,
Tailwind 4 with reka-ui, vue-i18n. OpenRouter for AI, over Server-Sent Events. PHPUnit 13 and Vitest, PHPStan level 6 with
kit-specific rules, Pint and oxlint. Bun, never npm. Never prettier.

## Documentation

`bun run docs:dev`, or read the sources:

- [Architecture](docs/architecture.md) - how the reactive loop, the registry and the types fit together
- [Adding a feature](docs/adding-a-feature.md) - the generator walkthrough
- [AI](docs/ai.md) - streamed actions, tools, prompt injection, cost
- [Runtime contract](docs/runtime-contract.md) - the image, the four processes, boot order, health, `kit:doctor`
- [Release procedure](docs/release.md) - tags, workflows, webhooks, rollback
- [Known limitations](docs/limitations.md) - what the design does not promise
- [CLAUDE.md](CLAUDE.md) - the conventions agents and reviewers work from

## Licence

MIT.
