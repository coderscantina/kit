---
name: notifications
description: The notification contract. Use when adding or changing a notification, a channel, the escalation ladder, notification preferences, the inbox, or SMS.
---

# Notifications

`docs/notifications.md` is the full contract.

Every notification lands in the `notifications` inbox first; everything after that is a ladder, not a fan-out. Declare a configurable one with `#[NotificationType]` on a class extending `App\Notifications\AppNotification` in `app/Notifications`. The attribute is the whole registration: `NotificationRegistry` discovers it and the preferences screen is built from what it finds. A notification **without** the attribute is transactional (password reset, verification code) and stays out of preferences on purpose.

`channels` on the attribute is the ladder in escalation order. Rung 0 goes out with the inbox row; every later rung is queued with a delay and checks `read_at` before it sends, so a person who saw the push never gets the mail. That is Laravel's own `withDelay()` and `shouldSend()`, not a job you write. SMS is on a ladder only when a type names it, the user switches it on, and the number has answered its code.

`read_at` is what the app calls "seen"; with `archived_at` it gives the three states unseen/seen/archived. A missing `notification_preferences` row means "has not chosen" and takes the type's defaults; an empty channel list is a deliberate "inbox only". The two must stay distinct.

The inbox is the kit's reactive surface: `notifications.list` and `notifications.summary`, both declaring `Dep::eq('notifications', 'notifiable_id', $userId)`. Bulk state changes go through `App\Actions\Notifications\UpdateInboxState`, which does one UPDATE and records one owner-scoped `Change` rather than a table-wide invalidation.

SMS ships as a seam: `App\Services\Sms\SmsSender` plus a `log` driver. Bind your own and name it in `config/sms.php`. `kit:doctor` fails a production box still on the log driver.
