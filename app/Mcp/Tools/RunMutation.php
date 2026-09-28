<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Illuminate\Contracts\Auth\Authenticatable;
use Kit\Reactive\Runtime\MutationRunner;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
class RunMutation extends ReactiveTool
{
    protected string $name = 'run_mutation';

    protected string $description = 'Change data: run one of the mutations list_operations returns, in a transaction, as the token owner. Every open screen sees the change live, and the record history names the owner. An edit to a versioned row takes the `version` it read; a stale one is refused with the current row.';

    public function handle(Request $request, MutationRunner $runner): Response
    {
        $class = $this->catalog->mutation((string) $request->get('name'));

        if ($class === null) {
            return Response::error('Unknown mutation. list_operations has the names.');
        }

        return $this->answer(
            $request,
            fn (Authenticatable $user, array $args): mixed => $runner->run(app($class), $user, $args)->result,
        );
    }
}
