<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A browser holding a session, keyed by the hash of the session id so the
 * table never holds a replayable cookie value. Plain Eloquent rather than the
 * app base model: the key is not a ULID, and touching this row on every
 * request must not wake reactive subscriptions.
 *
 * @property string $id
 * @property string $user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $last_active_at
 * @property Carbon|null $revoked_at
 * @property Carbon $created_at
 */
class UserSession extends Eloquent
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = ['id', 'user_id', 'ip_address', 'user_agent', 'last_active_at', 'created_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_active_at' => 'datetime',
            'revoked_at' => 'datetime',
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

    /**
     * @param  Builder<UserSession>  $query
     * @return Builder<UserSession>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    /** The lookup key for a session id. The raw id is never stored. */
    public static function key(string $sessionId): string
    {
        return hash('sha256', $sessionId);
    }
}
