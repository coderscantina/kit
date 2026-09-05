<?php

declare(strict_types=1);

namespace Kit\Reactive\Tests\Unit;

use Kit\Reactive\Invalidation\Change;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Change::class)]
final class ChangeTest extends TestCase
{
    #[Test]
    public function values_for_a_column_cover_before_and_after_without_duplicates(): void
    {
        $change = new Change('t', '1', ['c' => 'a', 'x' => '1'], ['c' => 'b', 'x' => '1']);

        $this->assertSame(['a', 'b'], $change->valuesFor('c'));
        $this->assertSame(['1'], $change->valuesFor('x'));
        $this->assertSame([], $change->valuesFor('missing'));
        $this->assertSame(['a'], (new Change('t', '1', ['c' => 'a'], null))->valuesFor('c'));
    }

    #[Test]
    public function it_round_trips_through_arrays_for_the_queue(): void
    {
        $change = new Change('t', '1', null, ['c' => 'a']);

        $this->assertEquals($change, Change::fromArray($change->toArray()));
        $this->assertTrue(Change::table('t')->isTableWide());
    }
}
