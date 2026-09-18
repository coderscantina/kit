---
name: presence
description: The presence contract. Use when showing who is on a screen, online status, who is editing a field, cursors, typing indicators, or whispers.
---

# Presence

`docs/presence.md` is the full contract and the reasons behind it.

Who is on a screen is not a query. `usePresence('<resource>')` joins `presence.<resource>` over Echo; `App\Support\Presence` opens it to whoever has `<resource>.view`, falling back to `app.access`. Nothing to register: the one callback in `routes/channels.php` covers every resource-level roster. A row-scoped roster needs its own callback with a real policy check.

A roster comes from `usePresence`, a status indicator from `UserAvatar` plus `usePresenceStatus`, and field-level "who is editing this" from `provideFieldPresence` on the form.

Whispers (`whisper` / `onWhisper`) go client to client and never reach PHP, so they carry cursors, typing flags and selections, and nothing that has to survive a refresh. Throttle a stream of them.

A form opts into whispering the value as typed with `{ values: true }`. Values stay private by default, and a password or a field listed in `secret` is never sent.
