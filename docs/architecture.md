# Architecture

## Layout

One folder per layer, one subfolder per domain inside the layers that have
enough of them to need it.

```
app/
  Actions/<Domain>/      plain classes that do one thing: CreateUser, AcceptInvite
  Ai/Actions/            streamed AI actions
  Data/                  every laravel-data class, args classes included
  Http/                  controllers, form requests, filters, middleware
  Jobs/                  QueuedJob and its subclasses
  Models/                Eloquent models, all of them, on the App\Models\Model base
  Mutations/<Domain>/    reactive mutations
  Policies/              ResourcePolicy and the concrete policies
  Queries/<Domain>/      reactive queries
  Services/<Domain>/     the stateful pieces: Ai, Auth, Account
  Support/               FeatureGate, RuntimeConfigPayload, Ambient, Export
config/abilities.php     the ability registry
database/               migrations, factories, seeders
packages/reactive/       PHP: base classes, registry, runner, worker, PHPStan rules
packages/reactive-vue/   TS: useReactiveQuery, useReactiveMutation, reconciliation
resources/js/            the SPA
tests/Feature/<Domain>/  the feature tests for that domain
docs/                    this site
```

Nothing registers anything. A model finds its policy and its factory by name,
migrations are picked up from `database/migrations`, and queries and mutations
are discovered from `app/Queries` and `app/Mutations` by the
`#[ReactiveQuery]` / `#[ReactiveMutation]` attribute. `config/reactive.php`
`discovery` is the one place that says where to look.

Deleting a feature means deleting its files across those folders. That is the
price of the layout, and it is paid by `grep`, not by a registry: the generators
name every file after the feature, so `Post` is `app/Models/Post.php`,
`app/Policies/PostPolicy.php`, `app/Queries/Post/`, `app/Mutations/Post/`,
`app/Data/Post*.php` and `tests/Feature/Post/`.

## The request pipeline

Validate, authorize, work, serialize. Each step has exactly one home.

For data that is `Query` and `Mutation`. Their base classes guarantee the
order, so a subclass only fills in `args()`, `authorize()` and `handle()`. For
the REST remainder (login, profile, uploads) it is a FormRequest, a policy call,
an action class and a `laravel-data` class. Models are never returned.

`args()` returns the laravel-data class the payload is validated into, and the
runner builds it with `Data::validateAndCreate()`. A bad payload is a 422
before `authorize()` runs. `Kit\Reactive\NoArgs` covers a query or mutation
that takes nothing.

The signatures read `handle(Data $args)` rather than `handle(ListPostArgs
$args)` because PHP forbids narrowing a parameter type in an override. The
concrete type reaches PHPStan through `@extends Query<ListPostArgs>`, which it
also checks against `args()`, so the two cannot drift and `$args->ownerId`
type-checks inside `handle()`.

Authorization is always a policy. `ResourcePolicy` maps `viewAny`/`view` to
`<resource>.view` and `create`/`update`/`delete` to `<resource>.manage`; a
policy with per-model rules (ownership, state) writes its methods out instead,
because an inherited `create()` would turn a denial into an allow.

Every authenticated route group also carries the `app.access` middleware. The
per-endpoint check stays the primary layer; the group check exists so a single
forgotten `authorize()` is a 403 and not a data leak.

## The reactive loop

```
client                      server                       redis
  |  POST /rq/subscribe  ->  validate, authorize
  |                          first asker runs the query   rq:comp:{key}
  |                          collect tables from the SQL  rq:dep:* -> comp key
  |  <- result, id, mid      later askers read the store  rq:sub:{id} -> comp
  |  join private-subscription.{id}
  |
  |  POST /rq/mutate     ->  transaction (REPEATABLE READ, 3 attempts)
  |                          model hooks buffer changes
  |                          on commit: INCR mutation id  reactive:mutation_id
  |                          resolve the dep sets         rq:dep:* -> comp keys
  |  <- ResultChanged        up to 4 keys: recompute and push right here
  |  <- result, mid          more, or a busy lock: one Invalidate job  queue: reactive
  |
  |                          Invalidate resolves again on the worker
  |                          RecomputeComputation per key, 50 ms debounce
  |  <- ResultChanged        re-run once, hash; on a change
  |                          re-authorize and push per subscriber
```

The recompute runs in the writer's own request first. After commit the
`Invalidator` resolves which computations the batch touched; up to
`inline_recomputes` of them (4) it recomputes and pushes before the response
returns, so the other session's screen never waits on a queue, and the
writer's own screen has its push before its response. Anything beyond that
count, a lock another recompute holds, or a recompute that throws goes to the
`reactive` queue as before; a failure there can never fail the request, the
row is committed by then. `/rq/health` reports `inline` next to `recomputes`.

Measured on a laptop, one writer, a ten row page, `POST /rq/mutate` returning
to the other browser's row changing: through the queue with a worker on the
default three second poll, 839 to 2309 ms, median 1062 ms, and five of twelve
writes never showed because the next one coalesced over them. Inline, every
write shows and the median is a few tens of milliseconds; the numbers are in
the commit that made the change.

The 50 ms debounce is not a delay. It is an NX marker set when a recompute is
decided on and released when it starts reading, so a burst that arrives while
one is queued collapses onto it. Under a single writer it never fires and
costs nothing; it only pays when a worker is behind.

Four things decide the cost of a write:

- **Sharing.** A query's `handle()` sees only the validated args, never the
  user, so two clients asking the same question get the same answer and share
  one computation. A hundred tabs on one list are one row in the dep sets and
  one recompute, not a hundred. Authorization is the per-user half and stays on
  the subscription, re-checked before every push.
- **Table tracking.** Every `FROM` and `JOIN` the query executed becomes a
  dependency. Free, and always correct.
- **`reads()`.** Declaring `Dep::eq('posts', 'author_id', $id)` takes the
  computation _out_ of the table-level set, so a write to another author's row
  never reaches it. Declare it on tables over ~10k rows or with high write
  fan-out.
- **The hash.** A recompute that produces the same xxh3 hash pushes nothing,
  and costs no authorization calls either.

## Push shapes

Reverb drops frames over 10 KB. A serialized result under 8 KB rides inside
`ResultChanged`; anything larger is pushed as `{subscriptionId, mutationId,
hash}` and the client fetches through `/rq/query`. Both carry the mutation id,
and pushes for one subscription are emitted in increasing order, so
reconciliation is the same on either path.

`/rq/query` authorizes the caller and then answers from the computation's
stored result, so the fallback costs one Redis read rather than a re-run of the
query that was too large to inline. It only runs the query when nobody
subscribes to that question.

With no Reverb the client skips subscriptions entirely. `useReactiveQuery`
fetches through `/rq/query` and refetches every 30 seconds, so the app still
works, it just learns about a write on the next poll rather than on the push.
The same 30 second poll covers a socket that dropped, except there the
subscriptions survive and pushes resume on reconnect.

## Client reconciliation

`useReactiveForm(source, { fields })` binds a form to a live row. `fields`
are the `v-model` targets, `base` is the last row the server gave, and dirty
is a comparison between the two rather than a flag, so a field typed back to
its old value is clean again. Every new row from `source` (a push, usually)
lands in the clean fields and leaves the dirty ones; a dirty field whose server
value moved is listed in `conflicts` with the server's value, and `accept()`
takes it. A row with a different identity (`id`) replaces the form, edits
included, because that is a different record, not an update.

An optimistic patch is recorded against a pending id. When a push arrives with
mutation id `M`, the cache is set to the pushed result and every pending patch
whose mutation has not committed at or below `M` is re-applied on top. A
mutation that resolves with committed id `C` drops its patches from any key
already at `>= C`. Entries older than 10 seconds are dropped and their keys
refetched. That is what keeps a list from flickering while a second client
writes to it.

## Conflicts

A mutation says which version of the row it was written against, and the row
says no when that version has moved.

```php
// the model
use Kit\Reactive\Concurrency\Versioned;   // integer `version`: 1 on create, +1 per change

// the args
public int $version,

// the mutation
$card = $this->lockVersion(Card::query()->findOrFail($args->id), $args->version);
```

`lockVersion()` takes the row lock `lock()` takes and compares. A row at
another version throws `VersionConflict` inside the transaction, so nothing is
written, and the runner answers 409:

```json
{
  "message": "The row changed since it was read (version 3 expected, 4 found).",
  "code": "CONFLICT",
  "expected": 3,
  "actual": 4,
  "current": { "id": "01m1…", "version": 4, "name": "theirs", "...": "..." }
}
```

`current` is the row now, presented through the mutation's declared `result`
class, so the client holds three things: the row it read (`base`), what the
user typed (`fields`), and what the server has. That is enough to resolve
field by field. On the client the 409 is a `ConflictError<TResult>` and
`useReactiveMutation` takes an `onConflict` for it; a conflict that has a
handler never reaches the error toast. `useReactiveForm().apply(error.current)`
does the merge: fields the user left alone take the server's value, fields he
edited keep his and show up in `conflicts` with what the server has, and
`base.version` moves forward so the next save carries the current version.

Because an open form also takes pushes through the same `apply()`, a 409 is
the rare case: most of the time the form already holds the latest row when the
user saves. The 409 covers the race the push lost.

## Counters

Counters and recompute latencies live behind `Kit\Reactive\Contracts\Metrics`,
not on the registry: `Registry` is about subscriptions and computations, and
losing a counter never changes what a client sees. `RedisMetrics` keeps one
hash and one capped list; `ArrayMetrics` carries the sqlite suite. `/rq/health`
reads the snapshot.

## Octane

The process lives across requests. No request state in statics or singletons;
anything per-request goes through the container and into
`config('octane.flush')`. `config('kit.ambient_bindings')` is the declared list,
`QueuedJob` snapshots it around `execute()`, and `kit:doctor` fails when a
binding is missing from the flush list.

## Types

`php artisan types:generate` writes `resources/js/types/generated.d.ts`: every
`#[TypeScript]` data class as an `App.*` type, a `Kit.ReactiveMap` built from
every registered query and mutation, and the `window.__APP_CONFIG__`
declaration. A map entry's `args` is the name of the query's args class, so
giving it `#[TypeScript]` is what makes the client's arguments typed;
`NoArgs` renders as `Record<string, never>`. `--check` fails when the committed file is stale, and CI runs it,
so a name that does not exist on the server is a type error on the client.
