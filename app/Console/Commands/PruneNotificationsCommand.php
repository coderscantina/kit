<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

/**
 * An inbox nobody empties grows forever. Archived rows past the retention
 * window are the ones the owner already said they were done with, so they are
 * the only ones deleted; anything still in the inbox stays there however old
 * it is, because deleting something a person has not looked at would be the
 * app losing the message.
 */
class PruneNotificationsCommand extends Command
{
    protected $signature = 'notifications:prune {--days= : Delete archived notifications older than this many days}';

    protected $description = 'Delete archived notifications past the retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('notifications.retention_days', 90));

        if ($days < 1) {
            $this->components->info('Retention is off; nothing pruned.');

            return self::SUCCESS;
        }

        $deleted = Notification::query()
            ->whereNotNull('archived_at')
            ->where('archived_at', '<', now()->subDays($days))
            ->delete();

        $this->components->info("Deleted {$deleted} archived notification(s).");

        return self::SUCCESS;
    }
}
