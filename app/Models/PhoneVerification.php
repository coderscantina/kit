<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * A phone number waiting on the code texted to it.
 *
 * Same shape as EmailChange: one pending row per user, only the hash of the
 * code stored, an expiry, and a counter so a six digit code cannot be
 * guessed. The number does not reach `users.phone` until the code comes back.
 *
 * @property string $id
 * @property string $user_id
 * @property string $phone
 * @property string $code_hash
 * @property int $attempts
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['phone', 'code_hash', 'attempts', 'expires_at'])]
class PhoneVerification extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->expires_at->isFuture()
            && $this->attempts < (int) config('sms.verification.max_attempts', 5);
    }

    public function matches(string $code): bool
    {
        return Hash::check($code, $this->code_hash);
    }

    /**
     * Seconds a caller must wait before another code can be sent. The row's
     * own updated_at is the clock, so a resend is throttled per number rather
     * than per session.
     */
    public function resendCooldown(): int
    {
        $window = (int) config('sms.verification.resend_seconds', 60);
        $elapsed = (int) ($this->updated_at?->diffInSeconds(now()) ?? $window);

        return max(0, $window - $elapsed);
    }
}
