# Architecture

## Layout

```
app/
  Features/<Name>/     Models/ Queries/ Mutations/ Data/ Policies/ Tests/
                       Database/{Factories,Migrations}/ <Name>ServiceProvider.php
  Http/                the few REST controllers: auth, profile, invites
  Jobs/QueuedJob.php   base job
  Policies/            ResourcePolicy and the ones with real per-model rules
  Support/             FeatureGate, RuntimeConfigPayload, Ambient
config/abilities.php   the ability registry
packages/reactive/     PHP: base classes, registry, runner, worker, PHPStan rules
packages/reactive-vue/ TS: useReactiveQuery, useReactiveMutation, reconciliation
resources/js/          the SPA
docs/                  this site
```

A feature is a folder. Everything it owns lives inside it, including its
migrations and its tests, so deleting the folder deletes the feature. Its
service provider is discovered from the folder by `AppServiceProvider`, so
there is no list to keep in sync.

## The request pipeline

Validate, authorize, work, serialize. Each step has exactly one home.

For data that is `Query` and `Mutation`. Their base classes guarantee the
order, so a subclass only fills in `rules()`, `authorize()` and `handle()`. For
the REST remainder (login, profile, uploads) it is a FormRequest, a policy call,
an action class and a `laravel-data` class. Models are never returned.

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
  |  POST /rq/subscribe  ->  validate, authorize, run
  |                          collect tables from the SQL
  |  <- result, id, mid      union with reads()          rq:sub, rq:dep:*
  |  join private-subscription.{id}
  |
  |  POST /rq/mutate     ->  transaction (REPEATABLE READ, 3 attempts)
  |                          model hooks buffer changes
  |                          on commit: INCR mutation id  reactive:mutation_id
  |  <- result, mid          dispatch one Invalidate      queue: reactive
  |
  |                          Invalidate resolves the dep sets
  |                          RecomputeSubscription per id, 50 ms debounce
  |  <- ResultChanged        re-run, re-authorize, hash, push
```

Three things decide the fan-out:

- **Table tracking.** Every `FROM` and `JOIN` the query executed becomes a
  dependency. Free, and always correct.
- **`reads()`.** Declaring `Dep::eq('posts', 'author_id', $id)` takes the
  subscription *out* of the table-level set, so a write to another author's row
  never reaches it. Declare it on tables over ~10k rows or with high write
  fan-out.
- **The hash.** A recompute that produces the same xxh3 hash pushes nothing.

## Push shapes

Reverb drops frames over 10 KB. A serialized result under 8 KB rides inside
`ResultChanged`; anything larger is pushed as `{subscriptionId, mutationId,
hash}` and the client fetches through `/rq/query`. Both carry the mutation id,
and pushes for one subscription are emitted in increasing order, so
reconciliation is the same on either path.

## Client reconciliation

An optimistic patch is recorded against a pending id. When a push arrives with
mutation id `M`, the cache is set to the pushed result and every pending patch
whose mutation has not committed at or below `M` is re-applied on top. A
mutation that resolves with committed id `C` drops its patches from any key
already at `>= C`. Entries older than 10 seconds are dropped and their keys
refetched. That is what keeps a list from flickering while a second client
writes to it.

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
declaration. `--check` fails when the committed file is stale, and CI runs it,
so a name that does not exist on the server is a type error on the client.
