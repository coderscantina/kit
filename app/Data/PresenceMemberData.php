<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\User;
use App\Services\Account\AvatarStorage;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One person on a presence channel, as everyone else on it sees them.
 *
 * This is the payload the broadcasting auth response hands to every other
 * member, so it carries what a roster or a cursor needs and nothing more.
 * Email, role and abilities stay out: joining a channel must not disclose
 * more about a colleague than the page already does.
 */
#[TypeScript]
class PresenceMemberData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $avatarUrl,
        /** A stable colour for this person, for cursors and avatar rings. */
        public string $color,
    ) {}

    public static function fromUser(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            avatarUrl: AvatarStorage::url($user),
            color: self::color($user->id),
        );
    }

    /**
     * Derived from the id rather than stored, so the same person is the same
     * colour in every session and no column has to be kept in sync. Fixed
     * saturation and lightness keep it readable in both colour modes.
     */
    public static function color(string $id): string
    {
        return sprintf('hsl(%d 65%% 55%%)', crc32($id) % 360);
    }
}
