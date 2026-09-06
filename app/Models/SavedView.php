<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A named list state: one table's search term, filters, sort and page size,
 * stored so it can be reapplied. Private to the user who saved it.
 *
 * @property string $id
 * @property string $user_id
 * @property string $scope
 * @property string $name
 * @property array<string, string> $params
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['scope', 'name', 'params', 'is_default'])]
class SavedView extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'params' => 'array',
            'is_default' => 'boolean',
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
