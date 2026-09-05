<?php

declare(strict_types=1);

namespace App\Services\Ai\Dto;

/**
 * The four things a stream can say. `Done` and `Error` are terminal: the
 * transport stops writing after either, and the client stops reading.
 */
enum StreamEventType: string
{
    case Status = 'status';
    case Delta = 'delta';
    case Done = 'done';
    case Error = 'error';

    public function isTerminal(): bool
    {
        return $this === self::Done || $this === self::Error;
    }
}
