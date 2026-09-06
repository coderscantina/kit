# Presence

Who else is on this screen right now, and a back channel to tell them
something that is not worth a database write. Presence rides on Reverb
directly, not on the reactive layer: a roster is not a query result, nobody
should recompute anything when a tab closes, and none of it survives a
refresh.

Realtime off means presence off. `usePresence` returns an empty roster and
`supported` is false, so a page that uses it still renders.

## The channel

One channel per screen, named after the resource it belongs to:
`presence.users` is the roster of the people list. The resource segment names
the ability that opens it, so a new roster registers nothing:

```php
// app/Support/Presence.php
Presence::ability('users');    // 'users.view', because the registry knows it
Presence::ability('anything'); // 'app.access', the fallback
```

`routes/channels.php` holds the one callback:

```php
Broadcast::channel('presence.{resource}', fn (User $user, string $resource): ?array
    => Presence::join($user, $resource));
```

The payload every other member receives is `PresenceMemberData`: id, name,
avatar URL and a colour derived from the id. Email, role and abilities stay
out. Joining a roster must not disclose more about a colleague than the page
already does.

## Row-scoped presence

"Everyone looking at _this_ post" is a different question, and an ability
cannot answer it. Register that channel yourself and check the policy:

```php
Broadcast::channel('presence.posts.{post}', function (User $user, Post $post): ?array {
    return Gate::forUser($user)->allows('view', $post) ? Presence::member($user) : null;
});
```

`Presence::member()` is the same payload the generic channel returns, so both
rosters look identical on the client.

## The client

```ts
import { usePresence } from '~/composables/usePresence'
import { AvatarList } from '~/components/ui/avatar'

const { members, others, count, whisper, onWhisper } = usePresence('users')
```

```vue
<AvatarList :users="others" :max="5" />
```

`AvatarList` takes `{ id, name, avatar, color }`, so map `avatarUrl` onto
`avatar` and the ring colour comes along for free.

Pass a getter to follow a route parameter. The old channel is left as the new
one is joined:

```ts
const { members } = usePresence(() => `posts.${route.params.id}`)
```

Two components asking for the same resource share one Echo channel; the last
one to unmount leaves it.

## Showing a status

`UserAvatar` is `Avatar` plus an optional indicator. Without a `status` it
renders exactly what `Avatar` renders, because a dot that is always there
says nothing:

```vue
<UserAvatar
  :name="member.name"
  :avatar="member.avatarUrl"
  :border-color="member.color"
  :status="statusOf(member.id)"
/>
```

Three states, three tokens: `online` is `success`, `offline` is the muted
`input` grey, `unavailable` is `destructive`. Each carries an announced
sentence, so the state does not live in the colour alone. Pass `statusLabel`
when the app knows better than "unavailable" does.

`usePresenceStatus` is the roster as a lookup, so a list does not rebuild the
same filter per row:

```ts
const { statusOf, members } = usePresenceStatus('users', {
  unavailable: () => awayIds.value,
})
```

`unavailable` is never derived. Away, in a meeting and do not disturb are the
application's words, and presence has no opinion about them; the caller passes
the ids. `online` and `offline` come off the roster, and with realtime off
`statusOf` returns null: an empty roster is not evidence that anyone is
offline, and the indicator renders nothing rather than lie.

`AvatarList` passes a `status` on an entry straight through, so a roster can
show the same three states without a second component.

## Whispers

A whisper goes client to client through Reverb without touching PHP. That
suits cursors, typing flags and selections: state that is meaningless the
moment its sender disconnects. Anything that has to survive a refresh is a
mutation.

```ts
const { whisper, onWhisper } = usePresence('board')

onWhisper<{ x: number; y: number }>('cursor', ({ senderId, x, y }) => {
  cursors.value.set(senderId, { x, y })
})

const move = useThrottleFn((event: PointerEvent) => {
  whisper('cursor', { x: event.clientX, y: event.clientY })
}, 50)
```

`whisper()` stamps `senderId` onto the payload, because the wire carries only
what the sender sent and a cursor with no owner is useless.

Two limits are worth knowing. Reverb accepts client events only from channel
members (`accept_client_events_from`), so a whisper on a channel the user was
refused simply goes nowhere. And a connection may only send so many of them,
which is why the example throttles rather than sending on every `pointermove`.

## What it is not

- **Not durable.** Nothing is stored. A member is on the roster while the
  socket is open and gone a few seconds after it closes.
- **Not server-visible.** PHP cannot ask who is online; the roster lives in
  Reverb. A query that needs "is this user active" needs a column and a
  mutation that writes it.
- **Not a lock.** Two people editing the same row still both win. Presence
  shows you that it is about to happen; it does not prevent it.
