<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry in the account's security trail. Append-only: nothing updates or
 * deletes a row except the cascade when the account goes away.
 *
 * @property string $id
 * @property string $user_id
 * @property string $event
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<string, mixed>|null $context
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'event', 'ip_address', 'user_agent', 'context', 'created_at'])]
class SecurityEvent extends Model
{
    public const string SIGNED_IN = 'signed_in';

    public const string SIGNED_OUT = 'signed_out';

    public const string PASSWORD_CHANGED = 'password_changed';

    public const string EMAIL_CHANGE_REQUESTED = 'email_change_requested';

    public const string EMAIL_CHANGED = 'email_changed';

    public const string TWO_FACTOR_ENABLED = 'two_factor_enabled';

    public const string TWO_FACTOR_DISABLED = 'two_factor_disabled';

    public const string BACKUP_CODES_REGENERATED = 'backup_codes_regenerated';

    public const string SESSION_REVOKED = 'session_revoked';

    public const string SOCIAL_LINKED = 'social_linked';

    public const string SOCIAL_UNLINKED = 'social_unlinked';

    public const string DATA_EXPORTED = 'data_exported';

    public const string PHONE_VERIFIED = 'phone_verified';

    public const string PHONE_REMOVED = 'phone_removed';

    public const string TOKEN_CREATED = 'token_created';

    public const string TOKEN_REVOKED = 'token_revoked';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
