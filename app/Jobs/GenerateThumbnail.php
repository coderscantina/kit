<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Attachment;
use App\Services\Attachments\AttachmentStorage;
use App\Services\Attachments\Thumbnailer;
use Throwable;

/**
 * Decoding a photo takes long enough to keep off the upload request. The
 * list is live, so the preview appears on every open screen when this
 * saves; until then the client shows the file icon.
 */
class GenerateThumbnail extends QueuedJob
{
    public function __construct(
        public readonly string $attachmentId,
    ) {
        $this->onQueue('default');
    }

    protected function execute(): void
    {
        $attachment = Attachment::query()->find($this->attachmentId);

        if ($attachment === null || $attachment->thumbnail_path !== null) {
            return;
        }

        $storage = app(AttachmentStorage::class);
        $contents = $storage->contents($attachment);
        $thumbnail = $contents === null ? null : app(Thumbnailer::class)->make($contents);

        if ($thumbnail !== null) {
            $storage->writeThumbnail($attachment, $thumbnail);
        }
    }

    /** A missing preview is cosmetic; the file itself is stored and served. */
    protected function handleFailure(Throwable $e): void
    {
        report($e);
    }
}
