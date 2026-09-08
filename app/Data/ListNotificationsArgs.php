<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\NotificationStatus;
use Spatie\LaravelData\Attributes\Validation\Ulid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What `notifications.list` takes.
 *
 * `userId` is an argument rather than something the query reads off the
 * session, because a query's handle() never sees the caller: two subscribers
 * asking the same question share one computation, and handle() also runs on a
 * worker with no session. authorize() is where the caller is checked, and it
 * refuses any id but their own.
 *
 * A null `status` is the inbox: everything that has not been archived.
 */
#[TypeScript]
final class ListNotificationsArgs extends Data
{
    public function __construct(
        #[Ulid]
        public string $userId,
        public ?NotificationStatus $status = null,
    ) {}
}
