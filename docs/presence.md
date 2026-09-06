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

## Presence on a form field

While someone else has a field focused, everyone else on the form sees who,
and whether they have unsaved changes in it. It rides whispers, so it is
worthless the moment the sender disconnects, which is exactly right: an
abandoned "Grace is editing" would be worse than nothing.

One call turns it on for a whole form:

```ts
import { provideFieldPresence } from '~/composables/useFieldPresence'

provideFieldPresence('users')
```

Every `FormField` below it then joins in. Without that call a `FormField`
behaves exactly as it did: no channel, no listeners, not even a handler bound
to the wrapper.

The field name is the key, so both sessions have to agree on it: the `id` on
`~/components/FormField.vue`, the `name` on `~/components/ui/form/FormField.vue`.

### Why the wrapper and not the control

A field is rarely one element. An OTP input is six, a combobox is a button
and a listbox, a date picker is whatever reka-ui renders this week, and a
slot may hold a component that owns no DOM you can reach. So the handlers go
on the field wrapper and rely on `focusin` and `focusout`, which bubble where
`focus` and `blur` do not. One pair there covers every control the kit puts
inside a field, and stepping between two controls of the same field is not a
blur: the wrapper still holds the focus.

Changed or not is read the same way, off `input` and `change`, against the
value the control held when it took focus. A control that has no value of its
own is taken at its word: anything it emits counts as a change. One that
emits nothing at all, a reka-ui select say, has an escape hatch on
`ui/form/FormField`:

```vue
<FormField name="role" :dirty="role !== saved.role" />
```

### What goes over the wire

Who, which field, and changed yes or no. Never the value, never a keystroke,
never a selection range. A field value is the user's data and a whisper is
not the place for it, so the payload cannot carry it even by accident:

```json
{ "field": "invite-email", "dirty": true, "senderId": "01m1..." }
```

One whisper carries the whole of a sender's state, because a person holds one
field at a time. It is throttled at 50 ms, the same floor the cursor stream
takes, so tabbing through a form sends the last state of each window rather
than every transition.

State clears three ways: on blur, on unmount, and when the member leaves the
roster. The last one is what covers a closed laptop, which never gets to
release anything.

### What it looks like

The indicator is `UserAvatar`, so a field and a roster say the same thing the
same way. Focused reads as `online`; focused with changes reads as
`unavailable`, because red is the state that means do not save over this yet.
Both carry the sentence, not just the colour.

## What it is not

- **Not durable.** Nothing is stored. A member is on the roster while the
  socket is open and gone a few seconds after it closes.
- **Not server-visible.** PHP cannot ask who is online; the roster lives in
  Reverb. A query that needs "is this user active" needs a column and a
  mutation that writes it.
- **Not a lock.** Two people editing the same row still both win. Presence
  shows you that it is about to happen; it does not prevent it. Field
  presence is the same: it is a warning, not a mutex.
- **Not per tab.** A presence channel counts users, not connections, so two
  tabs signed in as the same person are one member. `others` drops that
  member because it is you, so the roster is empty, no avatar appears on a
  row or a field, and nothing says why. Testing presence needs two accounts,
  in two browser profiles or one of them a private window: one profile shares
  the session cookie and you get the same user twice.
