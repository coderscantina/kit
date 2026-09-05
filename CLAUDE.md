# CLAUDE.md

Laravel 13 + Vue 3 with a reactive data layer. Full docs in `docs/`.

## 1. Where things go

A feature is a folder: `app/Features/<Name>/{Models,Queries,Mutations,Data,Policies,Tests,Database}` plus `<Name>ServiceProvider.php`. Delete the folder, delete the feature. Its provider is discovered from the folder, so there is no list to update.

Do not put feature code in `app/Http` (only auth, profile and invites live there), do not add a controller for data, and do not put shared constants in a composable module. They go in `resources/js/lib/`.

## 2. The reactive contract

Always `php artisan make:query <feature>.<name>` and `make:mutation <feature>.<name>`. Never hand-roll a query or mutation class.

Inside a mutation, never call `DB::transaction`, `afterCommit`, or dispatch an invalidation. The base class does all three, and a PHPStan rule fails the build otherwise. Take row locks with `$this->lock($model)` inside `handle()`.

`authorize()` is abstract and must do real work. An empty body fails an architecture test.

Declare `reads()` with `Dep::eq(...)` on tables over 10k rows or with high write fan-out. Keep pushed results small: over 8 KB the push carries only a hash and the client pays an extra round trip.

## 3. Frontend contract

Data comes from `useReactiveQuery` / `useReactiveMutation` in `~/lib/reactive`. Never `fetch` `/rq/*` directly.

Composables are imported explicitly. Only `vue` and `vue-router` APIs are auto-imported; a directory auto-import silently drops a composable that imports a sibling, and typecheck, lint and tests all stay green while the app renders blank.

Run `php artisan types:generate` after any change to a data class, query or mutation, and commit the result. CI fails on a stale `generated.d.ts`.

## 4. Definition of done

`bin/gate` green locally. One test per query and per mutation; the generators write them, keep them meaningful. Tests use `#[Test]`, never a `@test` docblock, which PHPUnit 13 ignores silently.

## 5. Release

`bin/release` cuts `vYYYY.M.D-<shortsha>` from `main`, writes the changelog block from the commit subjects and tags the changelog commit. Rollback is re-running the receiver with the previous tag. See `docs/release.md`.

## 6. When kit:doctor fails

Redis, Horizon and Reverb warnings mean a service is down; restart it. A failure means the repository is wrong: an unregistered query name (run `types:generate`), an ambient binding missing from `config/octane.php` `flush` (add it to `config/kit.php` `ambient_bindings`), a `@test` docblock, or an `en`/`de` key mismatch. See `docs/runtime-contract.md`.

## 7. Accepted trade-offs

Decisions reviewers keep re-filing. They are deliberate.

**Table-level invalidation over-notifies.** Without a declared `reads()`, any write to a table wakes every subscription that read it. The recompute almost always hashes to the same result and pushes nothing, so the cost is CPU on the `reactive` queue, not traffic or flicker. Predicate inference from the query builder is on the backlog; declaring `reads()` is the fix today. Reviews leave this as accepted.

**Non-Eloquent writes are invisible.** Invalidation rides on model events, so `DB::table()->update()`, raw SQL and mass `Model::query()->update()` never reach the buffer and subscribers keep a stale result until the TTL. A PHPStan rule flags them inside `Mutations/`, and bulk writes use `withoutReactiveEvents()` plus an explicit `Invalidate::table(...)`. CDC from the binlog is the real fix and is out of scope for v1. Reviews leave this as accepted.
