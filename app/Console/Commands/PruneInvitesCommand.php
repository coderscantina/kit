<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Invite;
use Illuminate\Console\Command;

class PruneInvitesCommand extends Command
{
    protected $signature = 'invites:prune {--days=30 : Delete invites that expired, were accepted or declined more than this many days ago}';

    protected $description = 'Delete stale invitations';

    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));

        $deleted = Invite::query()
            ->where(fn ($query) => $query
                ->where('expires_at', '<', $cutoff)
                ->orWhere('accepted_at', '<', $cutoff)
                ->orWhere('declined_at', '<', $cutoff))
            ->delete();

        $this->components->info("Deleted {$deleted} stale invitation(s).");

        return self::SUCCESS;
    }
}
