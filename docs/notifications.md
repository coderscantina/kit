# Notifications

Every notification lands in the inbox first. Everything after that is an
attempt to get the person's attention somewhere else, and those attempts are a
ladder rather than a fan-out: the next rung only fires if nobody has looked
yet.

That is the whole design. A person who saw the browser push never gets the
email five minutes later, and never gets the text after that.

## The three surfaces

| Where                    | What                                                  |
| ------------------------ | ----------------------------------------------------- |
| The bell in the header   | unseen count, the newest eight, mark all as seen      |
| `/notifications`         | the full inbox, split into inbox, unseen and archived |
| `/account/notifications` | which notification reaches you on which channel       |

The bell and the page are reactive: `notifications.summary` for the badge and
`notifications.list` for the rows, two subscriptions rather than one, because
the badge is mounted on every screen and the list only where it is open. The
settings page is REST — it is a form the browser posts and reads back once,
with no second writer to race.

## Declaring a notification

The attribute is the whole registration.

```php
#[NotificationType(
    key: 'posts.published',
    group: 'content',
    channels: ['push', 'mail'],   // the ladder, in escalation order
    default: ['push'],            // on for an account that has not chosen
    required: [],                 // cannot be switched off
)]
class PostPublished extends AppNotification
{
    public function __construct(private readonly string $title) {}

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title];
    }

    public function toMail(object $notifiable): MailMessage { /* … */ }
    public function toWebPush(object $notifiable): WebPushMessage { /* … */ }
}
```

`NotificationRegistry` scans `app/Notifications` for the attribute, and the
preferences screen is built from what it finds. Nothing else registers
anything, which is why the screen cannot offer a switch for a notification
that was deleted or miss one that was just added.

**A notification without the attribute stays out of all of this.** That is the
right answer for transactional mail — password resets, email confirmation,
the phone verification code — which a user must not be able to turn off.

`key` is what the `type` column stores and what the client keys its copy off,
so it survives renaming the class. Keep the payload in `toArray()` to scalars
and small arrays: it is stored as JSON, pushed over a socket, and read months
later, when the models it came from may be gone.

## How the ladder runs

`AppNotification::via()` returns `database` plus the channels that survive
three filters, in the order the type declared them:

1. the type's own `channels`
2. the user's preference, with `required` forced in
3. deliverability — push needs a subscribed browser, SMS needs a verified
   phone, and both need the feature configured

The third filter is why a preference can be set before its prerequisite
exists. Switching SMS on and verifying a number works in either order, and
neither step leaves a dead rung in between.

Then Laravel does the rest. `withDelay($notifiable, $channel)` returns null
for rung 0 and `n × NOTIFICATIONS_ESCALATION_MINUTES` for rung n, so each
channel is queued as its own job with its own delay. `shouldSend()` on a later
rung reads the inbox row and declines if `read_at` or `archived_at` is set.

There is no escalation job and no scheduler entry. The queue's own delay is
the timer and the inbox row is the shared signal.

> On the `sync` queue connection a delay is not a delay. Every rung fires at
> once. That is a local-development quirk worth knowing rather than a bug.

## The inbox row

`notifications` is Laravel's own table with three columns added:

| Column        | What                                                  |
| ------------- | ----------------------------------------------------- |
| `channels`    | the ladder the notification was sent with             |
| `deliveries`  | channel → timestamp of what actually went out         |
| `archived_at` | the third state on top of the framework's read/unread |

So the table answers both halves of "why did I get a text about this?": what
was planned, and what happened. `channels` is written at insert time by
`InboxChannel`, not patched in afterwards, so the row is complete the first
time a subscriber sees it. `deliveries` is stamped by
`RecordNotificationDelivery` as each rung lands.

**`read_at` is what the app calls "seen".** It is the column the framework
ships and the signal that stops the ladder. Three states, two timestamps:

```
unseen   read_at is null
seen     read_at is set, archived_at is null
archived archived_at is set
```

`App\Models\Notification` carries `HasReactiveInvalidation`, which is what
makes the inbox subscribable at all. Without it a delivery would write a row
no subscription ever hears about.

## Moving rows around

Three mutations, all taking `{ userId, ids }` where a null `ids` means "all of
them in the state this acts on":

| Mutation                 | Null `ids` means                |
| ------------------------ | ------------------------------- |
| `notifications.markSeen` | mark every unseen one seen      |
| `notifications.archive`  | archive everything already seen |
| `notifications.restore`  | put everything archived back    |

Archiving a named notification marks it seen on the way out; archiving
_everything_ only takes what has already been seen, so a full inbox cannot be
swept away unread by one click.

Each is a single UPDATE — "mark all as read" on a neglected inbox is thousands
of rows, and a loop of saves would be thousands of round trips inside one
transaction. An UPDATE fires no model events, so `UpdateInboxState` records
one `Change` carrying the owner's id. The resolver dedupes computation keys,
so a single change against the right predicate wakes exactly that account's
subscriptions and nobody else's. A table-wide invalidation would have woken
every open inbox in the installation.

Both queries declare `Dep::eq('notifications', 'notifiable_id', $userId)`.
This is the table in the app most likely to take constant writes; without the
predicate every delivery to anyone would wake every open inbox.

Archived rows past `NOTIFICATIONS_RETENTION_DAYS` are deleted by
`notifications:prune`, scheduled daily. Nothing still in an inbox is ever
deleted by age: that would be the app losing a message the person never read.

## Channels

`push` and `mail` are registered under plain names rather than class strings,
so one vocabulary runs end to end: what a preference stores, what a delay is
keyed on, what a delivery record says.

Push is the same web push the [push notifications](/push-notifications) page
sets up — a VAPID key pair and a browser that allowed the prompt.

## SMS

The kit ships the seam and a `log` driver, not a provider account.

```php
interface SmsSender
{
    public function send(SmsMessage $message): void;
}
```

`SMS_DRIVER=log` writes the message to the application log, which is enough to
develop the phone verification flow end to end without a bill. Bind your own
implementation in a service provider and name it in `config/sms.php`:

```php
$this->app->singleton(SmsSender::class, fn () => new AcmeSmsSender(config('sms.drivers.acme')));
```

`FeatureGate::smsEnabled()` derives from `sms.driver` being set. Empty means
the installation has no SMS channel and no phone section at all, rather than
switches that can only fail. `kit:doctor` **fails** a production box still
running the log driver, because that writes verification codes into the
application log instead of sending them.

SMS is never on a ladder by default. A notification type has to name it in
`channels`, and the user has to switch it on, and the number has to have
answered its code.

## Phone verification

Same shape as an email change: nothing on the account moves until the new
address proves it can receive.

```
POST   /api/account/phone          send (or resend) a code to a number
POST   /api/account/phone/verify   hand the code back
DELETE /api/account/phone          drop the number
```

`phone_verifications` holds one pending row per user with the code's hash, an
expiry and an attempt counter — six digits are guessable, and for some people
this number is a second factor. The row dies at `sms.verification.max_attempts`
guesses. A wrong code and no pending row give the same message, because which
of the two it was is not the caller's business.

`User::routeNotificationForSms()` returns null until `phone_verified_at` is
set, so an unverified number is not a number the SMS channel will text. That
is the whole point of verifying it: nobody can point this app's messages at
somebody else's handset.

Both POSTs sit behind `throttle:sensitive`, and the resend cooldown is on the
pending row rather than the session, so asking from a second tab does not
double the messages.

## Preferences

One row per (user, notification type), in `notification_preferences`.

**A missing row is not "nothing enabled", it is "has not chosen"**, and the
type's declared defaults apply. That is why the endpoint writes a row for
every type in the payload, including the ones with nothing switched on: a
deliberate "inbox only" has to be distinguishable from silence.

Unknown types and channels are dropped rather than rejected. A client still
carrying last release's list should save the switches that do exist, not fail
the whole screen. `required` channels are written back whether the client sent
them or not, so the stored row reads the same as what will be delivered.

Switches save on change. A settings matrix with thirty switches and one Save
button is a screen people leave half-applied, and every cell here is
independently reversible. A failed save puts the switch back: the server's
answer is the state, never the optimistic guess.

## Client copy

Notification keys carry dots (`security.alert`) and vue-i18n reads a dot as a
level of nesting, so the message tree uses underscores and
`messageKeyFor()` in `~/lib/notifications` is the one place that knows it:

```
notifications.types.security_alert.label   the preferences row
notifications.types.security_alert.hint    the line under it
notifications.types.security_alert.title   the inbox row
notifications.types.security_alert.body    the inbox row, with `data` as parameters
```

A type this build has no copy for still renders — it falls back to the raw
key rather than leaving a blank row, which is what lets an old notification
survive a rename. Same for the icon and the click target, in
`notificationPresentation`.
