<?php

declare(strict_types=1);

namespace App\Mutations\Notifications;

use App\Actions\Notifications\UpdateInboxState;
use App\Data\NotificationScopeArgs;
use App\Data\NotificationSummaryData;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Kit\Reactive\Attributes\ReactiveMutation;
use Kit\Reactive\Mutation;
use Spatie\LaravelData\Data;

/**
 * Marks notifications seen, which is also what stops them escalating: a
 * later rung checks the row before it sends, so a person who opened the bell
 * never gets the email that was queued behind it.
 *
 * @extends Mutation<NotificationScopeArgs>
 */
#[ReactiveMutation('notifications.markSeen', result: NotificationSummaryData::class)]
final class MarkNotificationsSeen extends Mutation
{
    public function __construct(
        private readonly UpdateInboxState $inbox,
    ) {}

    public static function args(): string
    {
        return NotificationScopeArgs::class;
    }

    /** An inbox is one person's, and only its owner may move rows in it. */
    public function authorize(Authenticatable $user, Data $args): void
    {
        if ($user->getAuthIdentifier() !== $args->userId) {
            throw new AuthorizationException;
        }
    }

    /**
     * Answers with the counts rather than the rows: the badge is what the
     * caller was looking at, the list already has its own subscription, and
     * a result the size of the inbox would be pushed to every open tab.
     */
    public function handle(Data $args): NotificationSummaryData
    {
        $user = User::query()->findOrFail($args->userId);

        $this->inbox->markSeen($user, $args->ids);

        return NotificationSummaryData::forOwner($args->userId);
    }
}
