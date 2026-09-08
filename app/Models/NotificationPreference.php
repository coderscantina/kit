<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one user chose for one notification type.
 *
 * A missing row is not "nothing enabled", it is "has not chosen", and the
 * type's declared defaults apply. That is why the preferences endpoint writes
 * a row for every type the user touches rather than only the ones they
 * switched on: a deliberate "all off" has to be distinguishable from silence.
 *
 * @property string $id
 * @property string $user_id
 * @property string $type
 * @property array<int, string> $channels
 */
#[Fillable(['type', 'channels'])]
class NotificationPreference extends Model
{
    /** @use HasFactory<NotificationPreferenceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channels' => 'array',
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
