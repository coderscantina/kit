# People and account

Everything the kit ships for managing who has access and for letting them look
after their own account. Both surfaces are plain REST controllers under
`app/Http/Controllers/Account`, not reactive queries: they are small, they are
read rarely, and a subscription would buy nothing.

## The people surface

`/users` lists accounts and outstanding invitations as one table. The merge is
a SQL union in `App\Services\Account\PeopleDirectory`, not two requests stitched
together in the browser, because a page of a merged list has to be a page of the
merged list. Paginating two sources separately and concatenating them gives a
page 2 that skips rows.

The list is the reactive query `people.list` (`App\Queries\People\ListPeople`), so
an account or invitation that changes anywhere reaches every open page over
Reverb, and the page joins `presence.users` to show who else is looking. The
export stays REST. Args carry the viewer's id, and `authorize()` refuses any
id but the caller's.

Only the columns both tables really have are projected into the union
(`kind`, `id`, `sort_name`, `email`, `role_id`, `created_at`), so neither branch
has to invent a typed `NULL` for the other's columns, which is the part that
does not survive a move from SQLite to Postgres. The rows are hydrated from the
models afterwards, two queries per page rather than one per row.

A row carries two independent axes:

- **`kind`** is `user` or `invite`, and decides which actions the row has.
- **`state`** is `active`, `pending` or `expired`, and decides which badge it
  wears. An expired invitation is still an invitation.

`PersonData` also carries `canAssignRole`, `canRemove` and `canResend`, decided
by the policies on the server. The table component holds no role arithmetic of
its own, so the root and last-owner cases cannot drift between the two sides.

Each half is gated on its own ability. Someone with `invites.view` but not
`users.view` gets the invitations and nothing else, rather than a 403. The tab
counts are computed before the segment filter, so switching tabs does not move
the numbers.

| Method | Path                           | Notes                                                         |
| ------ | ------------------------------ | ------------------------------------------------------------- |
| GET    | `/api/people/export`           | The filtered list as a file; same parameters as `people.list` |
| POST   | `/api/invites/{invite}/resend` | New token, fresh expiry; the mailed token stops working       |

## The account pages

`/account` is one area with three pages: Profile, Security and Your data. The
parent route (`pages/account/Layout.vue`) holds the identity block and the
sub-nav, which is a sticky column on a wide screen and a scrollable row on a
phone, and the child routes render into it. The layout is the b10cks account
settings pattern with two changes: sections are two columns on a wide pane
(heading left, controls right) so the page scans by topic, and Security opens
with an overview row that links a red tile to the section that turns it green.

A page is `SettingsPage` (title, one line of intent) holding
`SettingsSection`s. A section takes a `title`, a `description`, an `id` for
hash links, and a `footer` slot for the button that commits it; sections are
separated by rules, not cards. `destructive` paints the heading red and is
reserved for what cannot be undone.

The pages are reachable from the account menu, the command palette and the
phone drawer, and from nowhere in the sidebar.

## Avatars

**Stored on a private disk, served through the app.** `config('kit.avatars.disk')`
defaults to `local`; `GET /api/users/{user}/avatar` streams the bytes behind the
same session everything else needs.

The alternative was the public disk plus a `storage:link`, which is one less
hop but makes every avatar world-readable by URL. For an installation that is
members-only, that is a leak. Serving through the app also means an S3 move is a
config change rather than a bucket policy, and it needs no symlink on deploy.

The URL carries a version derived from the stored path
(`/api/users/{id}/avatar?v=<hash>`), so a replaced avatar is a different URL and
the response can be `immutable` for a year without any cache having to be told.

**Cropping happens in the browser.** `AvatarField.vue` centre-crops to a square
and scales to 512px on a canvas before uploading. That keeps an image library
off the backend entirely: no `ext-gd` requirement, no Intervention dependency,
and the bytes crossing the wire are already the bytes we intend to keep. The
server still validates what arrives (`image`, an explicit mime allow list that
excludes SVG, a size cap and a dimension cap), because a client-side crop is a
convenience, never a control.

| Method | Path                       | Notes                                                    |
| ------ | -------------------------- | -------------------------------------------------------- |
| POST   | `/api/account/avatar`      | Multipart `avatar`; answers 200 with the updated account |
| DELETE | `/api/account/avatar`      |                                                          |
| GET    | `/api/users/{user}/avatar` | Authenticated; `Cache-Control: private, immutable`       |

## Changing the email address

The address on the account is what a password reset is mailed to. Moving it is
the lever every account takeover pulls, so it is a two-step flow rather than a
`PATCH`:

1. `POST /api/account/email` behind `password.confirmed` and `not-impersonating`
   parks the new address in `email_changes` and mails it a one-time link. Only
   the token hash is stored, as with invites.
2. The address currently on the account gets a notification at the same time,
   with no token in it. That mail can only warn, never confirm, and it arrives
   while the change can still be stopped.
3. `POST /api/account/email/confirm/{change}` is token-gated and needs no
   session, because the link is usually opened in whichever browser has the
   mailbox. Opening it proves control of the new inbox, so the address arrives
   **verified**, and the old address gets a receipt.

Until confirmation the account reports `pendingEmail` alongside `email`, so the
profile page can show what is waiting and offer to cancel it. Cancelling takes
no step-up: undoing a change you did not want should not be the hard part.

A change confirmed after the address was claimed by someone else fails with a
422 rather than colliding on the unique index.

| Method | Path                                  | Step-up                           |
| ------ | ------------------------------------- | --------------------------------- |
| POST   | `/api/account/email`                  | password, not while impersonating |
| DELETE | `/api/account/email`                  | none                              |
| POST   | `/api/account/email/confirm/{change}` | the token is the proof            |

## Signed-in devices

`user_sessions` holds one row per browser, beside the session store rather than
inside it, so the feature works whichever `SESSION_DRIVER` is configured. The
kit defaults to Redis, and the framework's own device list only exists for the
database driver.

Two decisions worth knowing:

**The primary key is the SHA-256 of the session id, never the id itself.** A
dump of this table cannot be replayed as a cookie. Laravel's own `sessions`
table stores the raw id, and accepts that; this one does not have to, because it
never needs to read the session payload back.

**Revocation is a flag, not a kill.** `revoked_at` is set, and the revoked
browser trips it on its next request, where `TrackUserSession` tears the session
down and answers 401 with `SESSION_REVOKED`. This is the same "effective on the
next request" contract `AuthenticateSession` already gives for a password
change. The cost is that a browser sitting idle stays technically alive until it
does something; the gain is that the table holds no replayable session id and
the feature is driver-agnostic. An idle attacker is, by definition, not doing
anything.

Tracking is one primary-key lookup per authenticated request. The same row
answers both "is this browser still allowed" and "does its last-seen stamp need
refreshing", and the write is throttled to once a minute, so a page view is a
read and not a write. Rows older than `session.lifetime` are pruned whenever the
list is read, which is often enough and needs no scheduled job.

A password change revokes every other device, so the list never shows sessions
that no longer work.

| Method | Path                              | Step-up                     |
| ------ | --------------------------------- | --------------------------- |
| GET    | `/api/account/sessions`           | none                        |
| DELETE | `/api/account/sessions/{session}` | password                    |
| DELETE | `/api/account/sessions`           | password; spares the caller |

## Security activity

`security_events` is an append-only trail of the things an owner would want to
notice: sign ins and outs, password changes, address changes at both ends, the
second factor going on or off, backup codes being replaced, devices being
signed out, and data exports. `App\Services\Account\SecurityLog` is the only
writer; add a constant to `App\Models\SecurityEvent` and a label under
`security.events` in the i18n files to add one.

The endpoint is always scoped to the caller. There is no route here that reads
another account's history.

| Method | Path                             |
| ------ | -------------------------------- |
| GET    | `/api/account/security-activity` |

## Data export

`GET /api/account/export` behind `password.confirmed` and `not-impersonating`
returns the account, its devices, its security trail and the invitations it
sent, as one JSON download.

Synchronous on purpose. An account's own record here is a handful of rows, and a
queued job with a signed download link would add a storage lifecycle and a
second expiry to reason about for no gain. A derived app that grows large
per-user tables should move this to a job; that is the point at which the
trade-off flips.

## Closing an account

`DELETE /api/account` behind `password.confirmed` and `not-impersonating`. The
UI adds a third gate: the address has to be typed out before the button works,
so a stray click cannot get past it. Deleting cascades to sessions, the security
trail and any pending address change, and `MembershipGuard` still refuses to
remove the last owner.

## What is deliberately not here

**Passkeys.** They are the highest-value modern addition to this surface and
they are not in the kit. Doing them properly means a WebAuthn library, a
credential table, challenge storage, attestation policy, a second path through
the login controller, and account-recovery rules for when the authenticator is
lost. Half of it would be worse than none: a login path that sometimes works is
a support burden, and a credential store without recovery locks people out.

**Notification preferences.** The kit sends exactly one category of mail, and it
is all transactional: verification, password reset, invitations, security
notices. None of it is optional, so a preferences table would be a screen of
switches that control nothing. Add it when the first digest or mention mail
arrives, not before.
