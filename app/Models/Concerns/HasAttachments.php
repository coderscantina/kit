<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Attachment;
use App\Services\Attachments\AttachmentStorage;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Lets files be attached to the model through `/api/attachments` and listed
 * live with the `attachments.list` query. The model's policy decides: `view`
 * reads the files, `update` adds and removes them.
 *
 * Deleting the record deletes its files. A soft delete keeps them, so a
 * restore brings the record back whole.
 *
 * @phpstan-ignore trait.unused
 */
trait HasAttachments
{
    public static function bootHasAttachments(): void
    {
        static::deleted(function (self $model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $storage = app(AttachmentStorage::class);
            $model->attachments()->each(fn (Attachment $attachment) => $storage->delete($attachment));
        });
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
