<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Account\SecurityLog;
use Illuminate\Support\Facades\DB;

/**
 * Drops the number and anything pending against it.
 *
 * SMS preferences are left alone. A user who removes a handset and adds
 * another back a minute later should not have to re-tick every switch, and a
 * preference for a channel that cannot be delivered is simply skipped.
 */
class RemovePhone
{
    public function __construct(
        private readonly SecurityLog $securityLog,
    ) {}

    public function execute(User $user): User
    {
        DB::transaction(function () use ($user): void {
            $user->phoneVerification()->delete();
            $user->phone = null;
            $user->phone_verified_at = null;
            $user->save();
        });

        $this->securityLog->record($user, SecurityEvent::PHONE_REMOVED);

        return $user;
    }
}
