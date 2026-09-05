<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Actions\AskAssistant;
use App\Http\Controllers\Ai\AiStreamController;
use App\Services\Ai\Events\AiUsageRecorded;
use App\Services\Ai\Runtime\AiRunner;
use App\Services\Ai\Testing\FakeAiDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * The endpoint contract. Everything that can be answered with a status code
 * is answered with one: the stream only opens once the request is known to
 * be valid, allowed and runnable.
 */
#[CoversClass(AiStreamController::class)]
#[CoversClass(AiRunner::class)]
#[CoversClass(AskAssistant::class)]
final class AiStreamEndpointTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_streams_the_answer_and_records_what_it_cost(): void
    {
        Event::fake([AiUsageRecorded::class]);
        $this->createAndActAs(role: 'admin');
        $driver = FakeAiDriver::swap('The ', 'answer');

        $response = $this->ask(['question' => 'Why is the sky blue?']);

        $response->assertOk();
        $this->assertStringStartsWith('text/event-stream', (string) $response->headers->get('Content-Type'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('"type":"delta"', $body);
        $this->assertStringContainsString('The answer', $body);

        // The question travels as fenced data, never as part of the standing
        // instruction the model is told to follow.
        $this->assertStringNotContainsString('sky blue', $driver->systemPrompt());
        $this->assertStringContainsString('Why is the sky blue?', $driver->userPrompt());

        Event::assertDispatched(AiUsageRecorded::class, fn (AiUsageRecorded $event): bool => $event->action === 'assistant.ask' && $event->usage->totalTokens() === 30);
    }

    #[Test]
    public function a_user_without_the_ability_is_forbidden(): void
    {
        $this->createAndActAs(role: 'member');
        FakeAiDriver::swap();

        $this->askJson(['question' => 'anything'])->assertForbidden();
    }

    #[Test]
    public function a_guest_is_unauthenticated(): void
    {
        FakeAiDriver::swap();

        $this->askJson(['question' => 'anything'])->assertUnauthorized();
    }

    #[Test]
    public function an_unknown_action_is_a_404_carrying_a_reason(): void
    {
        $this->createAndActAs(role: 'admin');
        FakeAiDriver::swap();

        $this->postJson('/api/ai/stream', ['action' => 'nope.missing', 'args' => []])
            ->assertNotFound()
            ->assertJsonPath('reason', 'unknown_action');
    }

    #[Test]
    public function a_blank_question_is_a_422_before_the_model_is_called(): void
    {
        $this->createAndActAs(role: 'admin');
        $driver = FakeAiDriver::swap();

        $this->askJson(['question' => '   '])->assertStatus(422);

        $this->assertSame([], $driver->calls);
    }

    #[Test]
    public function an_installation_without_a_provider_key_says_so(): void
    {
        $this->createAndActAs(role: 'admin');
        FakeAiDriver::swap()->unconfigured();

        $this->askJson(['question' => 'anything'])
            ->assertStatus(503)
            ->assertJsonPath('reason', 'not_configured');
    }

    /**
     * @param  array<string, mixed>  $args
     * @return TestResponse<Response>
     */
    private function ask(array $args): TestResponse
    {
        return $this->post('/api/ai/stream', ['action' => 'assistant.ask', 'args' => $args]);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return TestResponse<Response>
     */
    private function askJson(array $args): TestResponse
    {
        return $this->postJson('/api/ai/stream', ['action' => 'assistant.ask', 'args' => $args]);
    }
}
