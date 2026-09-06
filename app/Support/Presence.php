<?php

declare(strict_types=1);

namespace App\Support;

use App\Data\PresenceMemberData;
use App\Models\User;
use App\Services\Auth\AuthorizationService;

/**
 * Who may join a presence channel, and what the roster shows of them.
 *
 * A channel is named after the resource it belongs to: `presence.posts` is
 * the roster of the posts screen. The ability follows from that name, so a
 * new roster registers nothing: `presence.posts` needs `posts.view` when the
 * ability registry knows it, and `app.access` when it does not. That keeps
 * the channel exactly as private as the screen it sits on.
 *
 * Row-scoped presence (everyone looking at *this* post) is a different
 * question, because an ability cannot answer it. Register that channel in
 * routes/channels.php with its own policy check and call `member()` for the
 * payload; see docs/presence.md.
 */
final class Presence
{
    /** The channel name segment, kept to what a route parameter may carry. */
    public const RESOURCE_PATTERN = '/^[a-z][a-z0-9-]*$/';

    /**
     * The auth answer for `presence.<resource>`: the member payload when the
     * user may join, null when they may not.
     *
     * @return array<string, mixed>|null
     */
    public static function join(User $user, string $resource): ?array
    {
        if (preg_match(self::RESOURCE_PATTERN, $resource) !== 1) {
            return null;
        }

        return app(AuthorizationService::class)->can($user, self::ability($resource))
            ? self::member($user)
            : null;
    }

    /**
     * The ability joining `presence.<resource>` takes. Falls back to
     * `app.access` for a resource with no registered view ability, so a
     * roster is never more open than the application itself.
     */
    public static function ability(string $resource): string
    {
        /** @var array<int, string> $abilities */
        $abilities = config('abilities.abilities', []);
        $view = $resource.'.view';

        return in_array($view, $abilities, true) ? $view : 'app.access';
    }

    /**
     * The payload every other member of the channel receives. Broadcasting
     * puts it in `user_info`, so it has to carry the id itself: Echo hands
     * the client this array and nothing else.
     *
     * @return array<string, mixed>
     */
    public static function member(User $user): array
    {
        return PresenceMemberData::fromUser($user)->toArray();
    }
}
