<?php

declare(strict_types=1);

namespace Kit\Reactive\Concurrency;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Spatie\LaravelData\Data;

/**
 * The row moved between the read the client made and the write it sent.
 *
 * Thrown inside the transaction by `Mutation::lockVersion()`, so the
 * runner rolls back and nothing was written. Rendered as 409 with the row
 * as it is now, presented through the mutation's declared result class,
 * so the client holds base, its own edits and the current row and can
 * resolve field by field instead of overwriting.
 */
final class VersionConflict extends RuntimeException
{
    public const string CODE = 'CONFLICT';

    /** @var class-string<Data>|null */
    private ?string $presentAs = null;

    public function __construct(
        public readonly Model $current,
        public readonly int $expected,
        public readonly int $actual,
    ) {
        parent::__construct("The row changed since it was read (version {$expected} expected, {$actual} found).");
    }

    /**
     * The Data class the current row is serialized through; the mutation's
     * `result` from its attribute, set by the runner before rethrowing.
     *
     * @param  class-string<Data>|null  $class
     */
    public function presentAs(?string $class): self
    {
        $this->presentAs = $class;

        return $this;
    }

    /**
     * @return array{message: string, code: string, expected: int, actual: int, current: mixed}
     */
    public function payload(): array
    {
        $class = $this->presentAs;

        return [
            'message' => $this->getMessage(),
            'code' => self::CODE,
            'expected' => $this->expected,
            'actual' => $this->actual,
            'current' => $class === null ? $this->current->toArray() : $class::from($this->current)->toArray(),
        ];
    }

    public function render(): JsonResponse
    {
        return new JsonResponse($this->payload(), 409);
    }
}
