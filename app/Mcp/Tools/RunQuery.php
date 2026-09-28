<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Illuminate\Contracts\Auth\Authenticatable;
use Kit\Reactive\Runtime\QueryRunner;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class RunQuery extends ReactiveTool
{
    protected string $name = 'run_query';

    protected string $description = 'Read data: run one of the queries list_operations returns and get its result as JSON. A query never changes anything.';

    public function handle(Request $request, QueryRunner $runner): Response
    {
        $class = $this->catalog->query((string) $request->get('name'));

        if ($class === null) {
            return Response::error('Unknown query. list_operations has the names.');
        }

        return $this->answer(
            $request,
            fn (Authenticatable $user, array $args): mixed => $runner->run(app($class), $user, $args)->result,
        );
    }
}
