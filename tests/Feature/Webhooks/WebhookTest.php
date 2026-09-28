<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Jobs\DeliverWebhook;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Mutations\Webhooks\CreateWebhook;
use App\Mutations\Webhooks\SendTestWebhook;
use App\Queries\Webhooks\ListWebhookDeliveries;
use App\Queries\Webhooks\ListWebhookEvents;
use App\Services\Webhooks\WebhookFanOut;
use App\Services\Webhooks\WebhookSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(WebhookFanOut::class)]
#[CoversClass(WebhookSender::class)]
#[CoversClass(DeliverWebhook::class)]
#[CoversClass(CreateWebhook::class)]
#[CoversClass(SendTestWebhook::class)]
#[CoversClass(ListWebhookDeliveries::class)]
#[CoversClass(ListWebhookEvents::class)]
final class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private const string URL = 'https://93.184.215.14/hooks';

    #[Test]
    public function a_record_change_queues_one_delivery_per_listening_endpoint(): void
    {
        Queue::fake();
        $listening = $this->endpoint(['users.*']);
        $this->endpoint(['posts.created']);

        $user = User::factory()->create();

        $delivery = WebhookDelivery::query()->sole();
        $this->assertSame($listening->id, $delivery->webhook_endpoint_id);
        $this->assertSame('users.created', $delivery->event);
        $this->assertSame($user->id, $delivery->payload['data']['id']);
        $this->assertSame(['redacted' => true], $delivery->payload['data']['changes']['password']);
        Queue::assertPushed(DeliverWebhook::class, fn (DeliverWebhook $job): bool => $job->deliveryId === $delivery->id);
    }

    #[Test]
    public function a_delivery_is_signed_the_standard_webhooks_way(): void
    {
        Http::fake([self::URL => Http::response('ok')]);
        $endpoint = $this->endpoint(['*']);

        User::factory()->create();

        $delivery = WebhookDelivery::query()->sole();
        $this->assertSame(WebhookDelivery::SUCCEEDED, $delivery->status);
        $this->assertSame(1, $delivery->attempts);

        Http::assertSent(function (Request $request) use ($endpoint, $delivery): bool {
            $key = base64_decode(Str::after($endpoint->secret, 'whsec_'), true);
            $signed = $request->header('webhook-id')[0].'.'.$request->header('webhook-timestamp')[0].'.'.$request->body();

            return $request->header('webhook-id')[0] === $delivery->id
                && $request->header('webhook-signature')[0] === 'v1,'.base64_encode(hash_hmac('sha256', $signed, (string) $key, true));
        });
    }

    #[Test]
    public function a_failing_receiver_is_retried_then_given_up_on(): void
    {
        Queue::fake();
        Http::fake([self::URL => Http::response('nope', 500)]);
        $endpoint = $this->endpoint(['*']);
        $delivery = $endpoint->deliveries()->create(['event' => 'webhook.test', 'payload' => ['type' => 'webhook.test'], 'status' => WebhookDelivery::PENDING]);

        $sender = app(WebhookSender::class);

        $this->assertFalse($sender->attempt($delivery->load('endpoint')));
        $this->assertSame(WebhookDelivery::PENDING, $delivery->refresh()->status);
        $this->assertSame(500, $delivery->response_status);

        $sender->giveUp($delivery);
        $this->assertSame(WebhookDelivery::FAILED, $delivery->refresh()->status);
    }

    #[Test]
    public function an_endpoint_that_now_resolves_inside_the_network_is_never_called(): void
    {
        Http::fake();
        $endpoint = $this->endpoint(['*']);
        $endpoint->url = 'http://169.254.169.254/latest';
        $endpoint->save();

        User::factory()->create();

        $this->assertSame(WebhookDelivery::FAILED, WebhookDelivery::query()->sole()->status);
        Http::assertNothingSent();
    }

    #[Test]
    public function creating_an_endpoint_answers_with_its_secret_once_and_refuses_internal_urls(): void
    {
        $this->createAndActAs(role: 'admin');

        $created = $this->postJson('/rq/mutate', ['mutation' => 'webhooks.create', 'args' => ['url' => self::URL, 'events' => ['users.*']]])
            ->assertOk();
        $this->assertStringStartsWith('whsec_', $created->json('result.secret'));

        $list = $this->postJson('/rq/query', ['query' => 'webhooks.list', 'args' => []])->assertOk();
        $this->assertStringNotContainsString('whsec_', (string) $list->getContent());

        $this->postJson('/rq/mutate', ['mutation' => 'webhooks.create', 'args' => ['url' => 'http://localhost:8080/x', 'events' => ['*']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('url');
    }

    #[Test]
    public function a_test_ping_is_delivered_whatever_the_endpoint_listens_for(): void
    {
        Http::fake([self::URL => Http::response('ok')]);
        $this->createAndActAs(role: 'admin');
        $endpoint = $this->endpoint(['posts.created']);

        $this->postJson('/rq/mutate', ['mutation' => 'webhooks.test', 'args' => ['id' => $endpoint->id]])->assertOk();

        $this->assertSame(WebhookDelivery::SUCCEEDED, $endpoint->deliveries()->sole()->status);
    }

    #[Test]
    public function the_event_list_names_every_audited_type(): void
    {
        $this->createAndActAs(role: 'admin');

        $events = $this->postJson('/rq/query', ['query' => 'webhooks.events', 'args' => []])->assertOk()->json('result.events');

        $this->assertContains('users.updated', $events);
        $this->assertNotContains('users.restored', $events);
    }

    #[Test]
    public function a_member_cannot_see_or_change_webhooks(): void
    {
        $this->createAndActAs(role: 'member');

        $this->postJson('/rq/query', ['query' => 'webhooks.list', 'args' => []])->assertForbidden();
        $this->postJson('/rq/mutate', ['mutation' => 'webhooks.create', 'args' => ['url' => self::URL, 'events' => ['*']]])->assertForbidden();
    }

    /**
     * @param  array<int, string>  $events
     */
    private function endpoint(array $events): WebhookEndpoint
    {
        return WebhookEndpoint::query()->create([
            'url' => self::URL,
            'events' => $events,
            'secret' => WebhookEndpoint::generateSecret(),
        ]);
    }
}
