<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Actions\Notifications\UpdateInboxState;
use App\Models\Notification;
use App\Models\User;
use App\Mutations\Notifications\ArchiveNotifications;
use App\Mutations\Notifications\MarkNotificationsSeen;
use App\Mutations\Notifications\RestoreNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kit\Reactive\Facades\Reactive;
use Kit\Reactive\Testing\InteractsWithReactive;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(MarkNotificationsSeen::class)]
#[CoversClass(ArchiveNotifications::class)]
#[CoversClass(RestoreNotifications::class)]
#[CoversClass(UpdateInboxState::class)]
final class InboxMutationTest extends TestCase
{
    use InteractsWithReactive;
    use RefreshDatabase;

    #[Test]
    public function marking_everything_seen_empties_the_badge_in_one_write(): void
    {
        $user = $this->createAndActAs(role: 'member');
        Notification::factory()->count(3)->create(['notifiable_id' => $user->id]);
        Reactive::fake();

        $batches = Reactive::capturingInvalidations(function () use ($user): void {
            $this->mutate('notifications.markSeen', ['userId' => $user->id])
                ->assertOk()
                ->assertJsonPath('result.unseen', 0)
                ->assertJsonPath('result.total', 3);
        });

        $this->assertSame(0, Notification::query()->whereNull('read_at')->count());

        // One batch, and the change carries the owner so only this account's
        // subscriptions are woken rather than every inbox on the table.
        $this->assertCount(1, $batches);
        $this->assertSame($user->id, $batches[0]->changeObjects()[0]->after['notifiable_id'] ?? null);
    }

    #[Test]
    public function marking_a_named_notification_seen_leaves_the_others_alone(): void
    {
        $user = $this->createAndActAs(role: 'member');
        $target = Notification::factory()->create(['notifiable_id' => $user->id]);
        $other = Notification::factory()->create(['notifiable_id' => $user->id]);

        $this->mutate('notifications.markSeen', ['userId' => $user->id, 'ids' => [$target->id]])
            ->assertOk()
            ->assertJsonPath('result.unseen', 1);

        $this->assertNotNull($target->fresh()->read_at);
        $this->assertNull($other->fresh()->read_at);
    }

    #[Test]
    public function archiving_everything_leaves_the_unseen_ones_in_the_inbox(): void
    {
        $user = $this->createAndActAs(role: 'member');
        $unseen = Notification::factory()->create(['notifiable_id' => $user->id]);
        $seen = Notification::factory()->seen()->create(['notifiable_id' => $user->id]);

        $this->mutate('notifications.archive', ['userId' => $user->id])->assertOk();

        $this->assertNull($unseen->fresh()->archived_at);
        $this->assertNotNull($seen->fresh()->archived_at);
    }

    #[Test]
    public function archiving_a_named_unseen_notification_marks_it_seen_on_the_way_out(): void
    {
        $user = $this->createAndActAs(role: 'member');
        $notification = Notification::factory()->create(['notifiable_id' => $user->id]);

        $this->mutate('notifications.archive', ['userId' => $user->id, 'ids' => [$notification->id]])
            ->assertOk()
            ->assertJsonPath('result.unseen', 0)
            ->assertJsonPath('result.total', 0);

        $fresh = $notification->fresh();
        $this->assertNotNull($fresh->archived_at);
        $this->assertNotNull($fresh->read_at);
    }

    #[Test]
    public function restoring_puts_it_back_without_making_it_new_again(): void
    {
        $user = $this->createAndActAs(role: 'member');
        $notification = Notification::factory()->archived()->create(['notifiable_id' => $user->id]);

        $this->mutate('notifications.restore', ['userId' => $user->id, 'ids' => [$notification->id]])
            ->assertOk()
            ->assertJsonPath('result.total', 1)
            ->assertJsonPath('result.unseen', 0);

        $this->assertNull($notification->fresh()->archived_at);
    }

    #[Test]
    public function another_persons_inbox_cannot_be_touched(): void
    {
        $this->createAndActAs(role: 'member');
        $stranger = User::factory()->create();
        $theirs = Notification::factory()->create(['notifiable_id' => $stranger->id]);

        $this->mutate('notifications.markSeen', ['userId' => $stranger->id])->assertForbidden();

        $this->assertNull($theirs->fresh()->read_at);
    }
}
