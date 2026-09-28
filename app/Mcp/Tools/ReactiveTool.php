<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Kit\Reactive\Concurrency\VersionConflict;
use Kit\Reactive\Registry\Catalog;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Runs a reactive query or mutation for the token's owner through the same
 * runner the SPA's `/rq/*` calls take: args validated into the Data class,
 * `authorize()` against the owner's abilities narrowed to the token's, a
 * mutation in its transaction. The failures a model can act on come back
 * as tool errors, not protocol errors.
 */
abstract class ReactiveTool extends Tool
{
    public function __construct(
        protected readonly Catalog $catalog,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The operation name from list_operations, e.g. `notifications.list`.')->required(),
            'args' => $schema->object()->description('The arguments list_operations names for it. Omit when it takes none.'),
        ];
    }

    /**
     * @param  Closure(Authenticatable, array<string, mixed>): mixed  $call
     */
    protected function answer(Request $request, Closure $call): Response
    {
        $user = $request->user();
        $name = (string) $request->get('name');
        $args = $request->get('args') ?? [];

        if ($user === null) {
            return Response::error('Not authenticated.');
        }

        if (! is_array($args)) {
            return Response::error('`args` must be an object.');
        }

        try {
            return Response::json($call($user, $args));
        } catch (ValidationException $e) {
            return Response::error('Invalid arguments: '.json_encode($e->errors()));
        } catch (AuthorizationException) {
            return Response::error("Not allowed: the token, or its owner, lacks the ability {$name} needs.");
        } catch (ModelNotFoundException) {
            return Response::error('Not found.');
        } catch (VersionConflict $e) {
            return Response::error('Conflict: the row changed since that version was read. '.json_encode($e->payload()));
        }
    }
}
