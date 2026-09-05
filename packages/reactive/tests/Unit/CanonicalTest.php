<?php

declare(strict_types=1);

namespace Kit\Reactive\Tests\Unit;

use Kit\Reactive\Runtime\Canonical;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Canonical::class)]
final class CanonicalTest extends TestCase
{
    #[Test]
    public function key_order_does_not_change_the_hash_but_list_order_does(): void
    {
        $this->assertSame(Canonical::hash(['a' => 1, 'b' => [2, 3]]), Canonical::hash(['b' => [2, 3], 'a' => 1]));
        $this->assertNotSame(Canonical::hash(['b' => [2, 3]]), Canonical::hash(['b' => [3, 2]]));
    }

    #[Test]
    public function normalize_flattens_json_serializable_objects_to_arrays(): void
    {
        $object = new class implements \JsonSerializable
        {
            public function jsonSerialize(): array
            {
                return ['nested' => new \ArrayObject([])];
            }
        };

        $this->assertSame(['nested' => []], Canonical::normalize($object));
        $this->assertNull(Canonical::normalize(null));
    }

    #[Test]
    public function encode_keeps_unicode_and_slashes_readable(): void
    {
        $this->assertSame('{"name":"ä","url":"/a/b"}', Canonical::encode(['url' => '/a/b', 'name' => 'ä']));
    }
}
