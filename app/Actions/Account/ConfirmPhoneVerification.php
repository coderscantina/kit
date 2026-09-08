<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Account\SecurityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Takes the code back and, if it matches, makes the number a route.
 *
 * Every wrong guess is counted and the row dies at the limit, so a six digit
 * code cannot be walked through. A wrong code and no pending row give the
 * same message on purpose: which of the two it was is not the caller's
 * business.
 */
class ConfirmPhoneVerification
{
    public function __construct(
        private readonly SecurityLog $securityLog,
    ) {}

    public function execute(User $user, string $code): User
    {
        $verification = $user->phoneVerification()->first();

        if ($verification === null || ! $verification->isPending()) {
            $verification?->delete();

            throw ValidationException::withMessages(['code' => __('notifications.phone.invalid_code')]);
        }

        if (! $verification->matches($code)) {
            $verification->increment('attempts');

            throw ValidationException::withMessages(['code' => __('notifications.phone.invalid_code')]);
        }

        DB::transaction(function () use ($user, $verification): void {
            $user->phone = $verification->phone;
            $user->phone_verified_at = now();
            $user->save();

            $verification->delete();
        });

        $this->securityLog->record($user, SecurityEvent::PHONE_VERIFIED);

        return $user;
    }
}
