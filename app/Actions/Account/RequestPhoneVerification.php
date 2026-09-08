<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\PhoneVerification;
use App\Models\User;
use App\Notifications\PhoneVerificationNotification;
use App\Support\FeatureGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Texts a code to a number and parks it until the code comes back.
 *
 * Nothing on the account moves here, for the same reason an email change
 * moves nothing: the number is where a security alert lands, so it has to
 * prove it can receive before it becomes a route.
 *
 * A second request replaces the first, which is what makes "resend" work. The
 * cooldown is on the pending row rather than on the session, so asking from a
 * second tab does not double the messages.
 */
class RequestPhoneVerification
{
    public function execute(User $user, string $phone): PhoneVerification
    {
        if (! FeatureGate::smsEnabled()) {
            throw new NotFoundHttpException;
        }

        $pending = $user->phoneVerification()->first();

        if ($pending !== null && $pending->resendCooldown() > 0) {
            throw ValidationException::withMessages([
                'phone' => __('notifications.phone.too_soon', ['seconds' => $pending->resendCooldown()]),
            ]);
        }

        $code = $this->code();

        $verification = DB::transaction(function () use ($user, $phone, $code): PhoneVerification {
            $user->phoneVerification()->delete();

            return $user->phoneVerification()->create([
                'phone' => $phone,
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes((int) config('sms.verification.ttl_minutes', 10)),
            ]);
        });

        // Addressed to the number under verification, not to the account's
        // route: the account has no SMS route yet, which is the point.
        Notification::route('sms', $phone)->notify(new PhoneVerificationNotification($phone, $code));

        return $verification;
    }

    /**
     * Digits only, and never with a leading zero trimmed off by a cast: the
     * code is read aloud off a lock screen and typed into a box.
     */
    private function code(): string
    {
        $length = max(4, (int) config('sms.verification.code_length', 6));

        return str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
    }
}
