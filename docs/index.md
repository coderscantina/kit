# Kit

A Laravel 13 + Vue 3 application with a Convex-style data layer: the client
subscribes to server-side query functions, writes go through transactional
mutations, and a result that changes is pushed to everyone watching it.

Read [architecture](/architecture) for how the pieces fit, then
[adding a feature](/adding-a-feature) for the generator walkthrough. When
something is broken in production, [runtime contract](/runtime-contract) says
what runs where and [known limitations](/limitations) says what the design
does not promise.

## The short version

- A **query** is a class with `rules()`, `authorize()` and `handle()`. The base
  class validates, authorizes, tracks the tables the SQL touched and serializes
  the result. You never write a controller for it.
- A **mutation** is the same shape, wrapped in a transaction with deadlock
  retry. On commit it takes a mutation id and dispatches one invalidation batch.
  You never call `DB::transaction`, `afterCommit` or dispatch by hand.
- The **registry** in Redis knows which subscription depends on which table (or
  which `column = value` predicate). An invalidation resolves to a set of
  subscriptions, each recomputed once per 50 ms window.
- The **client** calls `useReactiveQuery('posts.list')`. Names, arguments and
  results are typed from the PHP classes by `php artisan types:generate`.

## Getting started

```sh
git clone <repo> && cd <repo>
bin/dev up
```

That builds the image, boots MariaDB and Redis, runs `kit:setup` and serves the
app on <http://localhost:8000>. The first account you register becomes the
owner and closes self-registration.

## Commands

| Command | Does |
|---|---|
| `bin/dev up` | build and start the local stack |
| `bin/gate` | everything CI runs, locally, in CI's order |
| `bin/release` | cut and push a CalVer release |
| `php artisan make:feature Post` | scaffold a feature and wire it up |
| `php artisan make:query posts.list` | a query, its data class and its test |
| `php artisan make:mutation posts.create` | a mutation, its data class and its test |
| `php artisan types:generate` | regenerate `resources/js/types/generated.d.ts` |
| `php artisan kit:doctor` | check the runtime, the registry and the suite |
