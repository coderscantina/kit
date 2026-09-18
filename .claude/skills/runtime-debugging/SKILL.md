---
name: runtime-debugging
description: Use when kit:doctor fails or warns, a reactive push does not arrive or arrives late, a worker runs stale code, or the local stack will not start.
---

# Runtime debugging

`docs/runtime-contract.md` is the full reference.

## The local stack

`bin/dev start` runs serve, Vite, Reverb and Horizon on the host and returns once the app answers; `bin/dev status` says which process died, and each writes `storage/logs/dev-<name>.log`. `bin/dev stop` stops exactly what `start` started. Horizon keeps the code it booted with, so restart the stack after changing anything a job or a recompute runs.

## kit:doctor

Redis, Horizon and Reverb warnings mean a service is down; restart it. A `reactive queue` warning means no worker took the probe, or the one that did started before the last change to the reactive code and is running stale code: restart it. A failure means the repository is wrong: an unregistered query name (run `types:generate`), an ambient binding missing from `config/octane.php` `flush` (add it to `config/kit.php` `ambient_bindings`), or an `en`/`de` key mismatch.

## A push that does not arrive

`php artisan reactive:inspect <name> --json` answers it from the registry, in this order:

1. No computation for the name: nobody subscribes. Check the page's query name and args.
2. `last_recompute` is null: no write reached it since the first subscribe. The write went around Eloquent (raw SQL, `DB::table()`, a mass `update()`), or `reads()` names a predicate the write did not match.
3. `changed` is false: it recomputed to the same hash, so there was nothing to push.
4. `result_inline` is false: the result is over 8 KB and the client fetches it by hash, one extra round trip.
