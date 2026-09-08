<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Notification;
use App\Models\User;
use App\Queries\Notifications\ListNotifications;
use App\Queries\Notifications\NotificationSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kit\Reactive\Facades\Reactive;
use Kit\Reactive\Testing\InteractsWithReactive;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ListNotifications::class)]
#[CoversClass(NotificationSummary::class)]
final class InboxQueryTest extends TestCase
{
    use InteractsWithReactive;
    use RefreshDatabase;

    #[Test]
    public function a_new_notification_pushes_into_the_open_inbox(): void
    {
        $user = $this->createAndActAs(role: 'member');
        Reactive::fake();

        $this->actingAsSubscriber($user, 'notifications.list', ['userId' => $user->id]);

        Notification::factory()->create([
            'notifiable_id' => $user->id,
            'data' => ['event' => 'password_changed'],
        ]);

        Reactive::assertPushed(
            'notifications.list',
            fn (mixed $result): bool => count($result) === 1 && $result[0]['status'] === 'unseen',
        );
    }

    #[Test]
    public function another_persons_inbox_is_forbidden(): void
    {
        $this->createAndActAs(role: 'member');
        $stranger = User::factory()->create();

        $this->postJson('/rq/subscribe', [
            'query' => 'notifications.list',
            'args' => ['userId' => $stranger->id],
        ])->assertForbidden();

        $this->postJson('/rq/subscribe', [
            'query' => 'notifications.summary',
            'args' => ['userId' => $stranger->id],
        ])->assertForbidden();
    }

    #[Test]
    public function the_status_argument_picks_the_three_states_apart(): void
    {
        $user = $this->createAndActAs(role: 'member');

        Notification::factory()->create(['notifiable_id' => $user->id]);
        Notification::factory()->seen()->create(['notifiable_id' => $user->id]);
        Notification::factory()->archived()->create(['notifiable_id' => $user->id]);

        $unseen = $this->ask('notifications.list', ['userId' => $user->id, 'status' => 'unseen']);
        $archived = $this->ask('notifications.list', ['userId' => $user->id, 'status' => 'archived']);
        $inbox = $this->ask('notifications.list', ['userId' => $user->id]);

        $this->assertCount(1, $unseen);
        $this->assertCount(1, $archived);
        // No status is the inbox: everything that has not been archived.
        $this->assertCount(2, $inbox);
    }

    #[Test]
    public function the_summary_counts_only_what_is_still_in_the_inbox(): void
    {
        $user = $this->createAndActAs(role: 'member');

        Notification::factory()->count(2)->create(['notifiable_id' => $user->id]);
        Notification::factory()->seen()->create(['notifiable_id' => $user->id]);
        Notification::factory()->archived()->create(['notifiable_id' => $user->id]);

        $summary = $this->ask('notifications.summary', ['userId' => $user->id]);

        $this->assertSame(2, $summary['unseen']);
        $this->assertSame(3, $summary['total']);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<mixed>
     */
    private function ask(string $name, array $args): array
    {
        return $this->postJson('/rq/query', ['query' => $name, 'args' => $args])
            ->assertOk()
            ->json('result');
    }
}
