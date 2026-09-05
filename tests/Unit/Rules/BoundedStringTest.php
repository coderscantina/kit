<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\BoundedString;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(BoundedString::class)]
final class BoundedStringTest extends TestCase
{
    #[Test]
    public function it_rejects_a_blank_string_that_plain_string_rules_let_through(): void
    {
        $plain = Validator::make(['name' => ''], ['name' => ['string', 'min:1', 'max:10']]);
        $bounded = Validator::make(['name' => ''], ['name' => [new BoundedString(1, 10)]]);

        $this->assertTrue($plain->passes());
        $this->assertTrue($bounded->fails());
    }

    #[Test]
    public function it_bounds_length_in_characters_not_bytes(): void
    {
        $this->assertTrue(Validator::make(['v' => 'ääää'], ['v' => [new BoundedString(1, 4)]])->passes());
        $this->assertTrue(Validator::make(['v' => 'ääääå'], ['v' => [new BoundedString(1, 4)]])->fails());
    }

    #[Test]
    public function it_treats_a_missing_value_as_required(): void
    {
        $this->assertTrue(Validator::make([], ['v' => [new BoundedString(1, 4)]])->fails());
        $this->assertTrue(Validator::make(['v' => 7], ['v' => [new BoundedString(1, 4)]])->fails());
    }
}
