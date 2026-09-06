<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An account at an identity provider, tied to a local account.
 *
 * No access or refresh token is stored: sign-in is all this is for, and a
 * token the app never calls with is a credential to lose rather than a
 * feature.
 *
 * @property string $id
 * @property string $user_id
 * @property string $provider
 * @property string $external_id
 * @property string|null $nickname
 * @property string|null $email
 * @property Carbon|null $last_used_at
 */
#[Fillable(['user_id', 'provider', 'external_id', 'nickname', 'email', 'last_used_at'])]
class UserSocialLink extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
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
