<?php

declare(strict_types=1);

namespace Kit\Reactive\Runtime;

enum RecomputeOutcome
{
    /** Recomputed, and pushed when the result changed. */
    case Done;

    /** Another recompute holds the lock; the caller decides whether to retry. */
    case Busy;

    /** No computation, no subscribers or no query: nothing left to recompute. */
    case Gone;
}
