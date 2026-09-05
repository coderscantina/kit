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

## Results over 8 KB cost an extra round trip

Reverb drops frames over 10 KB. A serialized result above 8 KB is pushed as a
hash only and the client fetches it through `/rq/query`. Correct, and one more
round trip. Page or narrow the query if it matters.

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
rolls its optimistic patch back. While the socket is down, subscriptions fall
back to polling `/rq/query` every 30 seconds.

## No multi-tenancy

Ability strings carry no scope (`posts.view`, not `workspace.posts.view`). That
is deliberate: when a tenant scope arrives, `can()` gains a scope argument and
the strings stay as they are. Until then, one instance is one tenant.
