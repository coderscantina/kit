<?php

declare(strict_types=1);

namespace Tests\Fixtures\Reactive;

use Spatie\LaravelData\Data;

final class NoteData extends Data
{
    public function __construct(
        public string $id,
        public string $ownerId,
        public string $title,
        public string $body,
    ) {}

    public static function fromModel(Note $note): self
    {
        return new self($note->id, $note->owner_id, $note->title, $note->body);
    }
}
