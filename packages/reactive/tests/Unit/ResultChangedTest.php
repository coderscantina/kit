<?php

declare(strict_types=1);

namespace Kit\Reactive\Tests\Unit;

use Kit\Reactive\Events\ResultChanged;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ResultChanged::class)]
final class ResultChangedTest extends TestCase
{
    #[Test]
    public function small_results_ride_inline_and_large_ones_are_hash_only(): void
    {
        config(['reactive.inline_result_bytes' => 64]);

        $small = new ResultChanged('sub', 7, 'h', ['ok' => true]);
        $large = new ResultChanged('sub', 8, 'h', ['blob' => str_repeat('x', 100)]);

        $this->assertSame(['subscriptionId' => 'sub', 'mutationId' => 7, 'hash' => 'h', 'result' => ['ok' => true]], $small->broadcastWith());
        $this->assertSame(['subscriptionId' => 'sub', 'mutationId' => 8, 'hash' => 'h'], $large->broadcastWith());
        $this->assertSame('private-subscription.sub', $small->broadcastOn()->name);
        $this->assertSame('ResultChanged', $small->broadcastAs());
    }
}
