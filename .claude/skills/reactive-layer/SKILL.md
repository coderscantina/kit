---
name: reactive-layer
description: The reactive contract. Use when writing or changing a query, a mutation, a live list or detail page, useReactiveQuery / useReactiveMutation, a form that edits a row (useReactiveForm, Versioned, 409 conflicts), or reads() / invalidation.
---

# The reactive layer

`docs/architecture.md` is the full contract, `docs/adding-a-feature.md` the walkthrough, `docs/limitations.md` the costs. The inbox (`app/Queries/Notifications`, `app/Mutations/Notifications`) is the shipped example.

## Generate

`php artisan make:query <feature>.<name>` and `make:mutation <feature>.<name>` write every query and mutation, with its test. `make:mutation <feature>.<name> --versioned` writes the edit path: the version lock, the 409 test and an edit dialog on `useReactiveForm`. Args and writes follow the columns in the feature's create migration, so add a column there first and generate after.

## Server

Arguments are a laravel-data class, not a `rules()` array. A query or mutation names it twice, in `@extends Query<ListPostArgs>` and in `args()`; PHPStan checks the two agree. The runner builds it with `Data::validateAndCreate()`, so a bad payload is a 422 before your code runs. `Kit\Reactive\NoArgs` is the args class for something that takes none. Give the args class `#[TypeScript]`: `types:generate` names it in `Kit.ReactiveMap`.

`handle()`, `authorize()` and `reads()` are declared `Data $args` because PHP forbids narrowing a parameter type in an override. The concrete type reaches PHPStan through the `@extends`, so `$args->ownerId` still type-checks.

A mutation's transaction, `afterCommit` and invalidation belong to the base class; a PHPStan rule fails the build on a `DB::transaction`, `afterCommit` or dispatched invalidation inside one. Take row locks with `$this->lock($model)` inside `handle()`.

`authorize()` is abstract and does real work. An empty body fails an architecture test.

A mutation that edits a row a form can hold states the version it read: the model uses `Kit\Reactive\Concurrency\Versioned`, the args carry `int $version`, and `handle()` takes the row with `$this->lockVersion($model, $args->version)`. A row that moved answers 409 with the current row; the client resolves, never overwrites. Plain `$this->lock()` is for a row no form holds open.

After commit, a write that wakes at most four computations recomputes and pushes them inside its own request; more than that goes to the `reactive` queue.

A query's `handle()` takes args only. Subscribers asking the same question share one computed result, and `handle()` also runs on a worker with no session, so `auth()`, `request()` and the session stay out of it: scope through args, check the caller in `authorize()`. A PHPStan rule enforces it.

Declare `reads()` with `Dep::eq(...)` on tables over 10k rows or with high write fan-out. Keep pushed results small: over 8 KB the push carries only a hash and the client pays an extra round trip.

## Client

Reactive data comes from `useReactiveQuery` / `useReactiveMutation` in `~/lib/reactive`, typed off `Kit.ReactiveMap`. `/rq/*` is theirs alone; a lint rule rejects a bare `fetch`.

A form bound to a row uses `useReactiveForm` from `~/lib/reactive`: pushes land in the fields the user has not touched, their edits stay, `conflicts` says where both sides met, and a 409 goes through the same `apply()`. Pass `onConflict` to `useReactiveMutation` for it.

## When a push does not arrive

`php artisan reactive:inspect <name> --json` shows each live computation: subscribers, the last recompute, whether it changed the result, inline or queued, and the result size. The `runtime-debugging` skill covers the rest.
