<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Attachment;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
final class AttachmentData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $mimeType,
        public int $size,
        public ?int $width,
        public ?int $height,
        public string $url,
        /** Null until the queued preview exists, and for anything that is not an image. */
        public ?string $thumbnailUrl,
        public ?string $uploadedBy,
        public string $createdAt,
    ) {}

    public static function fromModel(Attachment $attachment): self
    {
        return new self(
            id: $attachment->id,
            name: $attachment->name,
            mimeType: $attachment->mime_type,
            size: $attachment->size,
            width: $attachment->width,
            height: $attachment->height,
            url: "/api/attachments/{$attachment->id}",
            thumbnailUrl: $attachment->thumbnail_path === null ? null : "/api/attachments/{$attachment->id}/thumbnail",
            uploadedBy: $attachment->uploader?->name,
            createdAt: $attachment->created_at?->toIso8601String() ?? '',
        );
    }
}
