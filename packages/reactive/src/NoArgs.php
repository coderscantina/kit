<?php

declare(strict_types=1);

namespace Kit\Reactive;

use Spatie\LaravelData\Data;

/**
 * The args of a query or mutation that takes none.
 *
 * Every query and mutation names an args class, so "no arguments" is a
 * class rather than a special case in the runner. It renders on the client
 * as `Record<string, never>`.
 */
final class NoArgs extends Data {}
