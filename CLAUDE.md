# CLAUDE.md

Laravel 13 + Vue 3 starter kit. Two data paths, one layered layout. Full docs
in `docs/`. Each contract lives in a project skill that loads when the work
touches it: `reactive-layer`, `list-surfaces`, `ai-actions`, `notifications`,
`presence`, `runtime-debugging` (in `.claude/skills/`).

## 1. Where things go

One folder per layer, a domain subfolder inside the layers that need one. There
is no `app/Features`; do not reintroduce it.

```
app/Actions/<Domain>/    plain classes that do one thing (CreateUser, AcceptInvite)
app/Ai/Actions/          streamed AI actions
app/Data/                every laravel-data class, args classes included
app/Http/                controllers, form requests, filters, middleware
app/Listeners/           event listeners, wired in a service provider
app/Models/              every model, on the App\Models\Model base
app/Mutations/<Domain>/  reactive mutations
app/Notifications/       notifications; #[NotificationType] makes one configurable
app/Policies/            ResourcePolicy and the concrete policies
app/Queries/<Domain>/    reactive queries
app/Services/<Domain>/   the stateful pieces (Ai, Auth, Account)
app/Support/             FeatureGate, RuntimeConfigPayload, Ambient, Export
database/                migrations, factories, seeders
tests/Feature/<Domain>/  the PHP feature tests for that domain
resources/js/lib/        shared frontend constants; never a composable module
```

Nothing registers anything. `App\Models\Post` finds `App\Policies\PostPolicy`
and `Database\Factories\PostFactory` by name, migrations are read from
`database/migrations`, and queries and mutations are discovered from
`app/Queries` and `app/Mutations` through their attributes.
`config/reactive.php` `discovery` is the one place that says where to look.

## 2. Which data path a change belongs on

**Most of what is shipped is REST.** Auth, account, people, invites, saved
views, notification settings and the AI assistant are controller +
FormRequest + action class + laravel-data, called from
`resources/js/api/resources/*`.

**The reactive layer is what the generators produce, and what notifications
run on.** The inbox is the shipped example; `docs/adding-a-feature.md` is the
walkthrough.

Pick by surface, not by taste: a new list or detail view that wants live
updates goes reactive; something the browser posts a form to, uploads a file
to, or gets redirected back to (OAuth, downloads, SSE) stays REST. A reactive
query is called by the client directly, never through a controller.

## 3. Generate, then edit

Every query, mutation, filter, endpoint and AI action starts from a generator,
which writes the class, its test and the wiring. The generated code passes
`bin/gate` as written; `php artisan <command> --help` has the options.

| Command                                                              | Writes                                                                                                |
| -------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| `make:feature Post --fields="title:string body:text?" [--versioned]` | model, migration, factory, policy, data class, list query, page, tests, abilities, route, nav, labels |
| `make:query posts.<name>`                                            | a query and its test                                                                                  |
| `make:mutation posts.create`                                         | a mutation, its args and its test                                                                     |
| `make:mutation posts.update --versioned`                             | the edit path: version lock, 409 test, a `useReactiveForm` dialog                                     |
| `make:filter Post`                                                   | the list filter with its sort allow list and whitelist                                                |
| `make:endpoint posts.import`                                         | REST: controller, FormRequest, action, route, `api` resource method, test                             |
| `make:ai-action posts.summarize`                                     | an AI action, its args and its test                                                                   |

The feature's create migration is the one place a column is declared: the
later generators read the fields from it. Add a column there, then generate.

## 4. The loop

- `bin/gate --changed` while working: one line per check, the output of the
  one that failed, the generator round trip only when its inputs moved.
- `bin/gate` once before calling the work done.
- `bin/dev start` runs the stack on the host and returns when it answers;
  `/dev/login/<owner|admin|member>` signs in as a seeded account. `bin/dev stop`
  stops what it started.
- The project hooks format every file you write and rerun `types:generate`
  after a change to a data class, query, mutation or AI action. Commit
  `resources/js/types/generated.d.ts` with the change; CI fails on a stale one.

## 5. Frontend rules

Colour is a token from `resources/js/assets/css/app.css`, never a palette
shade. Pick the rung by nesting: `bg-sidebar` for the frame, `bg-surface` for
the canvas, `bg-card` for a boxed block, `bg-popover` for anything floating.
`docs/design-tokens.md` has the ladder, the fills and the contrast rules.

Everything modal dims the page with `overlayClass` from
`~/components/ui/overlay`, never its own scrim string.

A field that carries a button, an icon or a unit composes `InputGroup`;
`InputField` is already built that way, so `actions` and the `prepend` and
`append` slots reserve space instead of floating over the text.

Data reaches the client through three doors: an `api` resource in
`resources/js/api/resources/` for REST, `~/lib/reactive` for the reactive
layer, `useAiStream` for AI. A lint rule rejects a bare `fetch`.

Composables are imported explicitly. Only `vue` and `vue-router` APIs are
auto-imported; a directory auto-import silently drops a composable that imports
a sibling, and typecheck, lint and tests all stay green while the app renders
blank.

## 6. Migrations

Nothing here has shipped to a database anyone has to protect, so the schema is
kept as few files as it can be: the framework's three, plus
`create_app_tables` for everything the app adds. Change a column by editing
that file and re-running `php artisan migrate:fresh --seed`, not by stacking an
`ALTER` on top. A generated feature gets its own `create_<table>_table`, which
is the right shape once the feature is real.

## 7. Definition of done

`bin/gate` green locally, with its last line saying so. One test per query, per
mutation and per endpoint; the generators write them, keep them meaningful.
Tests use `#[Test]` attributes; PHPUnit 13 ignores a `@test` docblock silently,
and `tests/Architecture/NoDocblockTestAnnotationsTest.php` catches one.

`bin/gate` ends on `bin/generators-roundtrip`, which scaffolds a throwaway
`Fixture` feature with every field type and every generator, and checks it
passes everything. Any change to the generators, the stubs, or where files go
has to keep that green.

`bin/release` cuts a release from `main`; `docs/release.md` has the steps and
the rollback.

## 8. Accepted trade-offs

Decisions reviewers keep re-filing. They are deliberate.

**Table-level invalidation over-notifies.** Without a declared `reads()`, any
write to a table wakes every subscription that read it. The recompute almost
always hashes to the same result and pushes nothing, so the cost is CPU on the
`reactive` queue, not traffic or flicker. Predicate inference from the query
builder is on the backlog; declaring `reads()` is the fix today. Reviews leave
this as accepted.

**Non-Eloquent writes are invisible.** Invalidation rides on model events, so
`DB::table()->update()`, raw SQL and mass `Model::query()->update()` never
reach the buffer and subscribers keep a stale result until the TTL. A PHPStan
rule flags them inside `Mutations/`, and bulk writes use
`withoutReactiveEvents()` plus an explicit `Invalidate::table(...)`. CDC from
the binlog is the real fix and is out of scope for v1. Reviews leave this as
accepted.

**`config/reactive.php` duplicates the package's own config.** The published
copy is what the app reads; the file in `packages/reactive/config/` is the
package default. They are kept in sync by hand. Splitting the package out of
the monorepo is what removes the duplication.
