# Kit

A Laravel 13 + Vue 3 application with a Convex-style data layer: the client
subscribes to server-side query functions, writes go through transactional
mutations, and a result that changes is pushed to everyone watching it.

Read [architecture](/architecture) for how the pieces fit, then
[adding a feature](/adding-a-feature) for the generator walkthrough.
[App shell](/app-shell) covers what a user sees: the sidebar, the header, the
keyboard layer and the one file you edit to rebrand. [Lists and
filters](/lists) covers the query contract every list surface speaks, and the
saved views built on it. [Presence](/presence) covers the roster
and the whisper channel behind it. [Social login](/social-login) and [push
notifications](/push-notifications) are both off until configured. [AI](/ai)
covers the streamed model calls. When
something is broken in production, [runtime contract](/runtime-contract) says
what runs where and [known limitations](/limitations) says what the design
does not promise.

## The short version

- A **query** is a class with `args()`, `authorize()` and `handle()`. `args()`
  names a laravel-data class, and the base class builds the payload into it,
  authorizes, tracks the tables the SQL touched and serializes the result. You
  never write a controller for it.
- A **mutation** is the same shape, wrapped in a transaction with deadlock
  retry. On commit it takes a mutation id and dispatches one invalidation batch.
  You never call `DB::transaction`, `afterCommit` or dispatch by hand.
- A **computation** is one query with one set of args and its last result.
  Everyone asking that question shares it, so a hundred tabs on one list are
  one recompute, not a hundred. A **subscription** is one client watching one
  computation, and is what authorization and revocation act on.
- The **registry** in Redis knows which computation depends on which table (or
  which `column = value` predicate). An invalidation resolves to a set of
  computations, each recomputed once per 50 ms window; a result that changed is
  pushed to every subscriber whose `authorize()` still passes.
- An **AI action** is the same shape pointed at a model: `args()`,
  `authorize()`, a system message and a prompt. It streams to the client over
  Server-Sent Events, and the client calls it with `useAiStream('posts.summarize')`.
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

| Command                                      | Does                                                          |
| -------------------------------------------- | ------------------------------------------------------------- |
| `bin/dev up`                                 | build and start the local stack                               |
| `bin/gate`                                   | everything CI runs, locally, in CI's order                    |
| `bin/release`                                | cut and push a CalVer release                                 |
| `php artisan make:feature Post`              | scaffold a feature, its list query and a page that renders it |
| `php artisan make:query posts.list`          | a query, its data class and its test                          |
| `php artisan make:mutation posts.create`     | a mutation, its args and data classes and its test            |
| `php artisan make:ai-action posts.summarize` | a streamed AI action, its args class and its test             |
| `php artisan types:generate`                 | regenerate `resources/js/types/generated.d.ts`                |
| `php artisan ai:models`                      | the provider's model catalogue, with context window and price |
| `php artisan kit:doctor`                     | check the runtime, the registry and the message files         |
