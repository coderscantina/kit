<?php

declare(strict_types=1);

namespace Tests\Feature\Mcp;

use App\Mcp\Operations;
use App\Mcp\Tools\ListOperations;
use App\Mcp\Tools\RunMutation;
use App\Mcp\Tools\RunQuery;
use App\Models\Notification;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

#[CoversClass(Operations::class)]
#[CoversClass(ListOperations::class)]
#[CoversClass(RunQuery::class)]
#[CoversClass(RunMutation::class)]
#[CoversClass(AuthorizationService::class)]
final class McpServerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_operations_list_describes_queries_and_their_arguments(): void
    {
        [, $token] = $this->owner(['app.access']);

        $result = $this->callTool($token, 'list_operations', []);

        $this->assertFalse($result['isError']);
        $operations = json_decode($result['content'][0]['text'], true);
        $this->assertIsArray($operations);
        $this->assertIsArray($operations['queries']);

        $inbox = collect($operations['queries'])->firstWhere('name', 'notifications.list');
        $this->assertIsArray($inbox);

        $this->assertSame('The account\'s inbox, live.', $inbox['description']);
        $this->assertSame('string', $inbox['args']['userId']);
        $this->assertStringContainsString('"unseen"', $inbox['args']['status']);
    }

    #[Test]
    public function a_query_runs_as_the_token_owner(): void
    {
        [$user, $token] = $this->owner(['app.access']);
        Notification::factory()->create(['notifiable_id' => $user->id]);

        $result = $this->callTool($token, 'run_query', ['name' => 'notifications.list', 'args' => ['userId' => $user->id]]);

        $this->assertFalse($result['isError']);
        $this->assertCount(1, json_decode($result['content'][0]['text'], true));
    }

    #[Test]
    public function a_token_is_held_to_the_abilities_it_was_given_even_for_root(): void
    {
        [$user, $token] = $this->owner(['app.access'], root: true);

        $result = $this->callTool($token, 'run_query', ['name' => 'audit.history', 'args' => ['type' => 'users', 'id' => User::factory()->create()->id]]);

        $this->assertTrue($result['isError']);
        $this->assertStringStartsWith('Not allowed', $result['content'][0]['text']);

        $allowed = $this->owner(['app.access', 'users.view'], root: true)[1];
        $this->assertFalse($this->callTool($allowed, 'run_query', ['name' => 'audit.history', 'args' => ['type' => 'users', 'id' => $user->id]])['isError']);
    }

    #[Test]
    public function a_mutation_runs_and_a_bad_payload_comes_back_as_a_tool_error(): void
    {
        [$user, $token] = $this->owner(['app.access', 'webhooks.manage']);

        $result = $this->callTool($token, 'run_mutation', [
            'name' => 'webhooks.create',
            'args' => ['url' => 'https://93.184.215.14/hooks', 'events' => ['*']],
        ]);

        $this->assertFalse($result['isError'], $result['content'][0]['text']);
        $this->assertDatabaseCount('webhook_endpoints', 1);

        $invalid = $this->callTool($token, 'run_mutation', ['name' => 'webhooks.create', 'args' => ['url' => 'nope', 'events' => []]]);
        $this->assertTrue($invalid['isError']);
        $this->assertStringStartsWith('Invalid arguments', $invalid['content'][0]['text']);
    }

    #[Test]
    public function without_a_token_the_endpoint_answers_401(): void
    {
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
    }

    #[Test]
    public function a_session_does_not_open_the_endpoint(): void
    {
        $this->createAndActAs(role: 'owner');
        $this->app['auth']->forgetGuards();

        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
    }

    /**
     * @param  array<int, string>  $abilities
     * @return array{0: User, 1: string}
     */
    private function owner(array $abilities, bool $root = false): array
    {
        $user = $root ? User::factory()->root()->create() : User::factory()->create();
        $this->assignRole($user, 'owner');

        return [$user, $user->createToken('agent', $abilities)->plainTextToken];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{content: array<int, array{type: string, text: string}>, isError: bool}
     */
    private function callTool(string $token, string $tool, array $arguments): array
    {
        $this->app['auth']->forgetGuards();

        /** @var TestResponse<Response> $response */
        $response = $this->withToken($token)->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => $tool, 'arguments' => (object) $arguments],
        ])->assertOk();

        return $response->json('result');
    }
}
