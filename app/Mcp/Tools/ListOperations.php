<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Operations;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListOperations extends Tool
{
    protected string $name = 'list_operations';

    protected string $description = 'List every query and mutation this app offers, with a description and the arguments each takes. Call this first. Whether the token may run one is decided when it runs.';

    public function __construct(
        private readonly Operations $operations,
    ) {}

    public function handle(Request $request): Response
    {
        return Response::json($this->operations->describe());
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
