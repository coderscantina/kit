<?php

declare(strict_types=1);

namespace App\Services\Ai\Events;

use App\Services\Ai\Dto\AiUsage;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * One completed action, with what it cost. Fired instead of writing a ledger
 * table nobody asked for: an app that needs per-user budgets listens for this
 * and stores what it actually needs.
 */
final class AiUsageRecorded
{
    use Dispatchable;

    public function __construct(
        public readonly string $action,
        public readonly ?int $userId,
        public readonly AiUsage $usage,
    ) {}
}
