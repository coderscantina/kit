# Known limitations

What the design does not promise. None of these is a bug; each is a trade-off
taken on purpose, and the first two are also in `CLAUDE.md` so reviewers stop
re-filing them.

## No snapshot consistency across queries

Each subscription recomputes on its own. Two queries reading the same rows can
be pushed a moment apart, so a screen showing both can briefly show a state that
never existed in the database. Put anything that must agree in one query.

## Table-level invalidation over-notifies

Without a declared `reads()`, any write to a table wakes every subscription that
read it, even when the changed row is irrelevant. The recompute usually
produces the same hash and pushes nothing, so the cost is CPU on the `reactive`
queue rather than traffic. Declare `Dep::eq(...)` on tables over ~10k rows or
with high write fan-out and the fan-out collapses to the matching rows.

## Non-Eloquent writes are invisible

Invalidation rides on Eloquent model events. `DB::table()->update()`, raw SQL,
`Model::query()->update()` and anything a migration or another process does
never reaches the buffer, so subscribers keep the stale result until their TTL
expires. A PHPStan rule flags these inside `Mutations/`. For a deliberate bulk
write use `Model::withoutReactiveEvents()` plus one explicit
`Invalidate::table('posts')`; never `Model::withoutEvents()`, which also kills
the ULID hooks.

## Conflicts are resolved per field, not per character

A versioned mutation and `useReactiveForm` give you the three rows a merge
needs (read, mine, theirs) and resolve them one field at a time: a field only
one side changed takes that side, a field both changed is a conflict the user
settles. That is the whole promise. Two people typing into the same text field
at once do not get their keystrokes interleaved; the second to save sees the
first one's text next to his own and picks. Operational transforms and CRDTs
are out of scope: they are a document model, not a form model, and a form
that needs them (a shared notes pane, a live spec) should be a separate
document feature on a purpose-built store rather than a wider `Versioned`.

What to do instead, in order of how often it is enough: keep fields small so
two people rarely land in the same one, let the whispered value (`values: true`
on `provideFieldPresence`) show who is where before anyone saves, and split a
long text into several fields where the domain allows it.

A row that was deleted under an open form answers 404 from `findOrFail`, not
409: there is no current row to hand back. Treat a 404 on save as "gone" and
close the form.

## The writer's request pays for small recomputes

A write that wakes at most four computations recomputes and pushes them
before its response returns. That is what takes the queue off the path the
other session waits on, and it is why a dead worker no longer looks like a
dead layer for ordinary writes. The cost is the recompute inside the request:
a mutation on a table read by a slow query gets slower by that query. Declare
`reads()` so fewer computations wake, keep queries paged, or set
`inline_recomputes` to 0 to send everything to the queue and accept the
worker's pickup delay. Bulk invalidations (`Invalidate::table()`) always take
the queue.

## Revocation waits for a changed result

A recompute re-authorizes each subscriber only when the result actually
changed, because the common case is a recompute that hashes the same and pushes
nothing, and paying for one `authorize()` per subscriber there would undo the
sharing. Nothing stale is ever delivered: a push happens only after the pushing
subscriber's `authorize()` passed. What lags is the tear-down, so a user who
lost access keeps the last result they were allowed to see until the next
change. `AuthorizationService::invalidateUser()` purges their subscriptions
immediately on any role or ability change, and logout purges them too, so the
lag only shows for access that changed by some other route.

## Results over 8 KB cost an extra round trip

Reverb drops frames over 10 KB. A serialized result above 8 KB is pushed as a
hash only and the client fetches it through `/rq/query`. That fetch is answered
from the computation's stored result rather than by re-running the query, so it
costs a round trip and a Redis read, not a second query. Page or narrow the
query if the round trip itself matters.

## Single Reverb node

One node by default. Reverb's cleanup events (`ChannelRemoved`,
`ConnectionPruned`) are per node, so scaled out, a subscription whose tab closed
against another node survives until its 1 hour Redis TTL rather than the usual
five seconds. Multi-node needs `REVERB_SCALING_ENABLED` and a review of that
cleanup path.

## 200 subscriptions per user

`/rq/subscribe` answers 429 beyond that, arguments are capped at 16 KB, and
`/rq/*` is rate-limited per user. A page that wants more than 200 live queries
wants fewer, wider queries.

## No offline support

There is no write queue. A mutation attempted without a connection fails and
rolls its optimistic patch back. Reads degrade to polling: a mounted query
refetches `/rq/query` every 30 seconds whenever the socket is down or missing.

That covers two cases. A dropped socket keeps its server subscriptions and only
polls until Reverb comes back. An install with no Reverb at all never opens a
subscription in the first place, because nothing can push on it; every fetch
goes to `/rq/query` and the 30 second poll is the only freshness the client
gets.

## No social login

Email and password only. An earlier draft carried a `socialProviders` block in
`__APP_CONFIG__` with no route, controller or `services.social` config behind
it, so the switch did nothing; it is gone rather than half-built. Adding OAuth
means `laravel/socialite`, a redirect and callback route, and a decision about
whether a provider email may claim an existing account. That decision is an
account-takeover vector when it is made carelessly, so it belongs in a feature
with its own tests, not in the baseline.

## No multi-tenancy

Ability strings carry no scope (`posts.view`, not `workspace.posts.view`). That
is deliberate: when a tenant scope arrives, `can()` gains a scope argument and
the strings stay as they are. Until then, one instance is one tenant.
