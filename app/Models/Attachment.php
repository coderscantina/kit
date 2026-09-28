<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A file on a record that uses HasAttachments. Written and removed through
 * AttachmentStorage, which keeps the row and the file on the disk together.
 *
 * @property string $id
 * @property string $attachable_type
 * @property string $attachable_id
 * @property string|null $uploaded_by
 * @property string $disk
 * @property string $path
 * @property string $name
 * @property string $mime_type
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property string|null $thumbnail_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model $attachable
 * @property-read User|null $uploader
 */
#[Fillable(['uploaded_by', 'disk', 'path', 'name', 'mime_type', 'size', 'width', 'height', 'thumbnail_path'])]
class Attachment extends Model
{
    /** Raster formats a browser renders as a picture and nothing else. */
    public const array INLINE_IMAGES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function isImage(): bool
    {
        return in_array($this->mime_type, self::INLINE_IMAGES, true);
    }

    /**
     * @return MorphTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
