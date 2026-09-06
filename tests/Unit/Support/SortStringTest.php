<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Filtering\SortString;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SortString::class)]
final class SortStringTest extends TestCase
{
    #[Test]
    public function a_leading_sign_is_the_direction_and_a_bare_column_ascends(): void
    {
        $this->assertSame(
            [
                ['column' => 'name', 'direction' => 'asc'],
                ['column' => 'created_at', 'direction' => 'desc'],
                ['column' => 'email', 'direction' => 'asc'],
            ],
            SortString::parse('+name,-created_at,email', ['name', 'created_at', 'email']),
        );
    }

    #[Test]
    public function a_column_outside_the_allow_list_is_dropped_rather_than_passed_on(): void
    {
        $this->assertSame([], SortString::parse('-password', ['name']));
        $this->assertSame(
            [['column' => 'name', 'direction' => 'asc']],
            SortString::parse('password,name', ['name']),
        );
    }

    #[Test]
    public function the_column_count_is_capped(): void
    {
        $this->assertCount(2, SortString::parse('a,b,c,d', ['a', 'b', 'c', 'd'], 2));
    }

    #[Test]
    public function first_falls_back_when_the_string_names_nothing_sortable(): void
    {
        $this->assertSame(
            ['column' => 'name', 'direction' => 'asc'],
            SortString::first('-password', ['name', 'email'], 'name'),
        );

        $this->assertSame(
            ['column' => 'email', 'direction' => 'desc'],
            SortString::first('-email', ['name', 'email'], 'name'),
        );
    }
}
