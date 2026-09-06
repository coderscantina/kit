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
  |  <- result, mid          dispatch one Invalidate      queue: reactive
  |
  |                          Invalidate resolves the dep sets
  |                          RecomputeComputation per key, 50 ms debounce
  |  <- ResultChanged        re-run once, hash; on a change
  |                          re-authorize and push per subscriber
```

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

An optimistic patch is recorded against a pending id. When a push arrives with
mutation id `M`, the cache is set to the pushed result and every pending patch
whose mutation has not committed at or below `M` is re-applied on top. A
mutation that resolves with committed id `C` drops its patches from any key
already at `>= C`. Entries older than 10 seconds are dropped and their keys
refetched. That is what keeps a list from flickering while a second client
writes to it.

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
