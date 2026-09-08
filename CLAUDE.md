# CLAUDE.md

Laravel 13 + Vue 3 starter kit. Two data paths, one layered layout. Full docs
in `docs/`.

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
`resources/js/api/resources/*`. Read those before adding a surface that looks
like them.

**The reactive layer is what the generators produce, and what notifications
run on.** `make:feature` writes a query, a page and a mutation on it. The
inbox (`app/Queries/Notifications`, `app/Mutations/Notifications`, the bell and
`/notifications`) is the shipped example to read; `docs/adding-a-feature.md`
is the walkthrough.

Pick by surface, not by taste: a new list or detail view that wants live
updates goes reactive; something the browser posts a form to, uploads a file
to, or gets redirected back to (OAuth, downloads, SSE) stays REST. Do not put
a controller in front of a reactive query.

## 3. The reactive contract

Always `php artisan make:query <feature>.<name>` and
`make:mutation <feature>.<name>`. Never hand-roll a query or mutation class.
`make:feature` runs `make:query <resource>.list` for you, so a new feature
ships a page that renders real rows.

Arguments are a laravel-data class, not a `rules()` array. A query or mutation
names it twice, in `@extends Query<ListPostArgs>` and in `args()`; PHPStan
checks the two agree. The runner builds it with `Data::validateAndCreate()`, so
a bad payload is a 422 before your code runs. `Kit\Reactive\NoArgs` is the args
class for something that takes none. Give the args class `#[TypeScript]`:
`types:generate` names it in `Kit.ReactiveMap`.

`handle()`, `authorize()` and `reads()` are declared `Data $args` because PHP
forbids narrowing a parameter type in an override. The concrete type reaches
PHPStan through the `@extends`, so `$args->ownerId` still type-checks.

Inside a mutation, never call `DB::transaction`, `afterCommit`, or dispatch an
invalidation. The base class does all three, and a PHPStan rule fails the build
otherwise. Take row locks with `$this->lock($model)` inside `handle()`.

`authorize()` is abstract and must do real work. An empty body fails an
architecture test.

A mutation that edits a row a form can hold states the version it read:
the model uses `Kit\Reactive\Concurrency\Versioned`, the args carry
`int $version`, and `handle()` takes the row with
`$this->lockVersion($model, $args->version)`. A row that moved answers 409
with the current row; the client resolves, never overwrites. Plain
`$this->lock()` is for a row no form holds open.

After commit, a write that wakes at most four computations recomputes and
pushes them inside its own request; more than that goes to the `reactive`
queue. `docs/architecture.md` has the numbers and `docs/limitations.md` the
cost.

A query's `handle()` takes args only. Never read `auth()`, `request()` or the
session in it: subscribers asking the same question share one computed result,
and `handle()` also runs on a worker with no session. Scope through args, check
the caller in `authorize()`. A PHPStan rule enforces it.

Declare `reads()` with `Dep::eq(...)` on tables over 10k rows or with high
write fan-out. Keep pushed results small: over 8 KB the push carries only a
hash and the client pays an extra round trip.

## 4. Frontend contract

Reactive data comes from `useReactiveQuery` / `useReactiveMutation` in
`~/lib/reactive`. Never `fetch` `/rq/*` directly. REST data goes through an
`api` resource class in `resources/js/api/resources/`, never a bare `fetch`.

A form bound to a row uses `useReactiveForm` from `~/lib/reactive`: pushes
land in the fields the user has not touched, his edits stay, `conflicts`
says where both sides met, and a 409 goes through the same `apply()`. Pass
`onConflict` to `useReactiveMutation` for it. `docs/architecture.md` has the
contract.

Presence is not the reactive layer. A roster comes from `usePresence`, a
status indicator from `UserAvatar` plus `usePresenceStatus`, and field-level
"who is editing this" from `provideFieldPresence` on the form. All of it
rides whispers, so nothing survives the sender's socket. A form opts into
whispering the value as typed with `{ values: true }`; it is never the
default and never sent for a password or a field listed in `secret`.
`docs/presence.md` has the contract and the reason.

Composables are imported explicitly. Only `vue` and `vue-router` APIs are
auto-imported; a directory auto-import silently drops a composable that imports
a sibling, and typecheck, lint and tests all stay green while the app renders
blank.

Run `php artisan types:generate` after any change to a data class, query or
mutation, and commit the result. CI fails on a stale `generated.d.ts`.

## 5. The list contract

Every list surface speaks one query contract: `page`, `per_page`,
`sort` (`+column` / `-column`), the free-text `q`, and one parameter per
filter carrying `operator:value`. The URL is the state.

Filters and sorting come from an `App\Http\Filters\<Name>Filter` extending
`CodersCantina\Filter\AdvancedFilter`, applied with `->filter($filter)`. Never
splice request values into a builder, and never take a `sort` column without
an allow list: the value reaches `orderBy()`. Every filter class calls
`setWhitelistedFilters()` in its constructor — the whole query bag arrives at
`apply()`, and `limit`/`offset` are inherited helpers a client must not reach.
A hand-built query that has no model to sort against (the people union) parses
the string with `App\Support\Filtering\SortString`.

On the client, `useTableQueryState` owns the URL and produces `params`; the API
resource passes that bag through rather than re-mapping it. `TableFilter`
builds the chips, `SavedViews` stores them. Tables are never wrapped in a card.
Full contract in `docs/lists.md`.

## 6. The AI contract

An AI action is a class, not a controller: `php artisan make:ai-action <feature>.<verb>`
writes it to `app/Ai/Actions/`, its args class to `app/Data/` and its test to
`tests/Feature/<Feature>/`. Never add a controller for a prompt; there is one
endpoint, `/api/ai/stream`, and the action name selects the action.

The shape mirrors a query: `args()` names a laravel-data class (checked
against `@extends AiAction<XArgs>` by PHPStan), `authorize()` must do real
work, `system()` is the standing instruction and carries no per-request data
so the provider can cache the prefix, `prompt()` returns the request.

Everything the user or the database supplied goes through `Prompt::with()`,
which fences it under a nonced tag. Never concatenate user data into the
instruction string. A tool runs server-side with nobody watching, so it
authorizes every row it touches; an id the model produced is not proof.

The client calls `useAiStream('<name>')` from `~/composables/useAiStream`,
typed off `Kit.AiMap`. Never fetch `/api/ai/stream` directly. Run
`types:generate` after touching an action or its args class.

Test with `FakeAiDriver::swap(...)`: no network, no key, no bill. Full contract
in `docs/ai.md`.

## 7. Notifications

Every notification lands in the `notifications` inbox first; everything after
that is a ladder, not a fan-out. Declare a configurable one with
`#[NotificationType]` on a class extending `App\Notifications\AppNotification`
in `app/Notifications`. The attribute is the whole registration:
`NotificationRegistry` discovers it and the preferences screen is built from
what it finds. A notification **without** the attribute is transactional
(password reset, verification code) and stays out of preferences on purpose.

`channels` on the attribute is the ladder in escalation order. Rung 0 goes out
with the inbox row; every later rung is queued with a delay and checks
`read_at` before it sends, so a person who saw the push never gets the mail.
That is Laravel's own `withDelay()` and `shouldSend()`, not a job you write.
SMS is never on a ladder unless a type names it and the user switches it on
and the number has answered its code.

`read_at` is what the app calls "seen"; with `archived_at` it gives the three
states unseen/seen/archived. A missing `notification_preferences` row means
"has not chosen" and takes the type's defaults; an empty channel list is a
deliberate "inbox only". The two must stay distinct.

The inbox is the kit's reactive surface: `notifications.list` and
`notifications.summary`, both declaring
`Dep::eq('notifications', 'notifiable_id', $userId)`. Bulk state changes go
through `App\Actions\Notifications\UpdateInboxState`, which does one UPDATE
and records one owner-scoped `Change` rather than a table-wide invalidation.

SMS ships as a seam: `App\Services\Sms\SmsSender` plus a `log` driver. Bind
your own and name it in `config/sms.php`. `kit:doctor` fails a production box
still on the log driver. `docs/notifications.md` has the contract.

## 8. Presence

Who is on a screen is not a query. `usePresence('<resource>')` joins
`presence.<resource>` over Echo; `App\Support\Presence` opens it to whoever
has `<resource>.view`, falling back to `app.access`. Nothing to register: the
one callback in `routes/channels.php` covers every resource-level roster. A
row-scoped roster needs its own callback with a real policy check.

Whispers (`whisper` / `onWhisper`) go client to client and never reach PHP,
so they carry cursors, typing flags and selections, and nothing that has to
survive a refresh. Throttle a stream of them. Full contract in
`docs/presence.md`.

## 9. Migrations

Nothing here has shipped to a database anyone has to protect, so the schema is
kept as few files as it can be: the framework's three, plus
`create_app_tables` for everything the app adds. Change a column by editing
that file and re-running `php artisan migrate:fresh --seed`, not by stacking an
`ALTER` on top. A generated feature gets its own `create_<table>_table`, which
is the right shape once the feature is real.

## 10. Definition of done

`bin/gate` green locally. One test per query and per mutation; the generators
write them, keep them meaningful. Tests use `#[Test]`, never a `@test`
docblock, which PHPUnit 13 ignores silently;
`tests/Architecture/NoDocblockTestAnnotationsTest.php` is what catches it.

`bin/gate` ends on `bin/generators-roundtrip`, which scaffolds a throwaway
`Fixture` feature and checks it passes everything. Any change to the
generators, the stubs, or where files go has to keep that green.

## 11. Release

`bin/release` cuts `vYYYY.M.D-<shortsha>` from `main`, writes the changelog
block from the commit subjects and tags the changelog commit. Rollback is
re-running the receiver with the previous tag. See `docs/release.md`.

## 12. When kit:doctor fails

Redis, Horizon and Reverb warnings mean a service is down; restart it. A
`reactive queue` warning means no worker took the probe, or the one that did
started before the last change to the reactive code and is running stale
code: restart it. A failure means the repository is wrong: an unregistered
query name (run `types:generate`), an ambient binding missing from
`config/octane.php` `flush` (add it to `config/kit.php` `ambient_bindings`), or
an `en`/`de` key mismatch. See `docs/runtime-contract.md`.

## 13. Accepted trade-offs

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
