<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Ulid;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What `notifications.summary` takes. Same reasoning as
 * {@see ListNotificationsArgs}: the owner is an argument, and authorize()
 * refuses any id but the caller's.
 */
#[TypeScript]
final class NotificationSummaryArgs extends Data
{
    public function __construct(
        #[Ulid]
        public string $userId,
    ) {}
}
