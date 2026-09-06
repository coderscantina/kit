# Push notifications

Native web push, delivered by the browser's push service and shown by the
service worker — so a notification arrives with the app closed and the tab
gone.

Off by default: without a VAPID key pair there is no push feature at all, and
the account page hides the switch rather than offering one that can only fail.

## Setting it up

**1. Generate a key pair, once.**

```sh
php artisan webpush:vapid
```

That writes `VAPID_PUBLIC_KEY` and `VAPID_PRIVATE_KEY` into `.env`. Keep them.
The public key is baked into every subscription a browser has already made, so
rotating it silently orphans every device that opted in.

**2. Set the subject.** `VAPID_SUBJECT` is a URL or `mailto:` the push service
can use to reach you about your traffic. It defaults to `APP_URL`.

**3. Migrate.** `push_subscriptions` holds one row per browser, keyed by the
endpoint its push service issued.

**4. Serve over HTTPS.** Push needs a secure context. `localhost` counts;
`http://192.168.x.x` does not.

Set `APP_FEATURE_PUSH=false` to turn it off without removing the keys.

## Sending one

A notification with a `toWebPush()` method and the channel:

```php
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class PostPublished extends Notification
{
    public function via(mixed $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(mixed $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title(__('posts.published_title'))
            ->body($this->post->title)
            ->icon('/icons/icon-192.png')
            ->tag("post-{$this->post->id}")
            // Where a click lands. Read by public/sw-push.js.
            ->data(['url' => "/posts/{$this->post->id}"]);
    }
}
```

`$user->notify(new PostPublished($post))` fans out to every browser the
account has signed up. `App\Notifications\TestPushNotification` is the
smallest working example, and is what the account page's "Send a test" button
uses.

A `tag` replaces an earlier notification with the same tag rather than
stacking a second one. Use it for anything that supersedes itself.

## The client

`public/sw-push.js` holds the `push` and `notificationclick` handlers. It
cannot live in the service worker itself, because Workbox generates that file
from the build; `workbox.importScripts` in `vite.config.ts` pulls it in. On a
click it focuses a tab that is already on this origin and navigates it, rather
than opening a second window.

`usePushNotifications()` is the state the UI needs, and keeps three cases
apart because their recoveries differ:

- **unsupported** — no service worker, no `PushManager`, or no VAPID key. The
  section says so and offers nothing.
- **blocked** — the user denied the permission. Only the browser's own site
  settings can undo that, so the UI says that instead of offering a button
  that silently fails.
- **not subscribed** — the ordinary case, and what the switch is for.

The permission prompt fires from the switch, never on page load. A prompt on
load is the one people deny reflexively, and a denial is expensive to undo.

## Endpoints

|                                             |                                                           |
| ------------------------------------------- | --------------------------------------------------------- |
| `POST /api/account/push-subscriptions`      | sign this browser up; re-subscribing updates the same row |
| `DELETE /api/account/push-subscriptions`    | drop one endpoint, or every device when the body has none |
| `POST /api/account/push-subscriptions/test` | send the test notification to this account                |

## iOS

Safari delivers web push only to a site the user has added to the home screen.
The install prompt is already in the shell (`InstallDialog`); until the app is
installed, `Notification.requestPermission()` throws and the section reports
the browser as unsupported. That is accurate, and nothing here can improve it.
