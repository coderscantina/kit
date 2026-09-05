<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Services\Ai\Support\JsonExtractor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonExtractor::class)]
final class JsonExtractorTest extends TestCase
{
    #[Test]
    public function it_decodes_the_three_shapes_a_model_produces(): void
    {
        $this->assertSame(['a' => 1], JsonExtractor::decode('{"a":1}'));
        $this->assertSame(['a' => 1], JsonExtractor::decode("```json\n{\"a\":1}\n```"));
        $this->assertSame(['a' => 1], JsonExtractor::decode('Sure! Here you go: {"a":1} Let me know.'));
        $this->assertSame([1, 2], JsonExtractor::decode("Here:\n[1, 2]"));
    }

    #[Test]
    public function a_brace_inside_a_string_does_not_close_the_object(): void
    {
        $raw = <<<'TXT'
        prose {"text":"a } and a \" quote"} more prose
        TXT;

        $this->assertSame(['text' => 'a } and a " quote'], JsonExtractor::decode($raw));
    }

    #[Test]
    public function nothing_parseable_is_null_rather_than_an_exception(): void
    {
        $this->assertNull(JsonExtractor::decode(null));
        $this->assertNull(JsonExtractor::decode('   '));
        $this->assertNull(JsonExtractor::decode('I could not do that.'));
        $this->assertNull(JsonExtractor::decode('{"unclosed": '));
    }
}
