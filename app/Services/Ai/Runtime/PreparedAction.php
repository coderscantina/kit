<?php

declare(strict_types=1);

namespace App\Services\Ai\Runtime;

use App\Services\Ai\AiAction;
use Spatie\LaravelData\Data;

/**
 * A named action whose payload has been validated and whose caller has been
 * authorized. Everything that can answer with a normal HTTP status has
 * already happened by the time one of these exists; what is left can only be
 * reported inside the stream.
 */
final readonly class PreparedAction
{
    /**
     * @param  AiAction<Data>  $action
     */
    public function __construct(
        public string $name,
        public AiAction $action,
        public Data $args,
        public ?int $userId,
    ) {}
}
