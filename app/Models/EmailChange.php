<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A requested address change. The address does not move until the link mailed
 * to it is opened, so a hijacked session cannot silently take over the
 * account by pointing password resets at an attacker's inbox.
 *
 * @property string $id
 * @property string $user_id
 * @property string $new_email
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 */
#[Fillable(['user_id', 'new_email', 'token_hash', 'expires_at'])]
#[Hidden(['token_hash'])]
class EmailChange extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
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
        return $this->expires_at->isFuture();
    }

    public function tokenMatches(string $token): bool
    {
        return hash_equals($this->token_hash, self::hashToken($token));
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
