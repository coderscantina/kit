<?php

declare(strict_types=1);

namespace App\Services\Ai\Contracts;

/**
 * A function the model may call mid-stream. Whatever `execute()` returns is
 * JSON-encoded and handed back to the model, so return plain arrays.
 *
 * A tool runs with the caller's privileges but without the caller watching:
 * authorize inside execute(), and never take an id from the model as proof
 * that the user may see that row.
 */
interface AiTool
{
    public function name(): string;

    public function description(): string;

    /**
     * JSON Schema for the arguments, as the provider expects it.
     *
     * @return array<string, mixed>
     */
    public function schema(): array;

    /**
     * A short present-tense line shown to the user while this runs.
     */
    public function status(): string;

    /**
     * @param  array<string, mixed>  $input
     */
    public function execute(array $input): mixed;
}
