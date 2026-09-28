<?php

declare(strict_types=1);

namespace App\Mcp;

use App\Mcp\Tools\ListOperations;
use App\Mcp\Tools\RunMutation;
use App\Mcp\Tools\RunQuery;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * The app's reactive queries and mutations, for a model. Three tools rather
 * than one per operation: the catalog grows with every generated feature,
 * and a model reads one list more reliably than it scans fifty tools.
 * Authentication is a personal access token; see routes/ai.php.
 */
#[Name('Kit')]
#[Version('1.0.0')]
#[Instructions('Call list_operations first: it names every query (read) and mutation (write) with its arguments. Run them with run_query and run_mutation. You act as the owner of the access token, limited to the abilities the token was given; a refusal means the token lacks one, not that the operation is broken.')]
class KitServer extends Server
{
    protected array $tools = [
        ListOperations::class,
        RunQuery::class,
        RunMutation::class,
    ];
}
