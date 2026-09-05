<?php

declare(strict_types=1);

namespace Kit\Reactive\Tests\Unit;

use App\Rules\BoundedString;
use Illuminate\Validation\Rule;
use Kit\Reactive\TypeScript\RulesToTypeScript;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RulesToTypeScript::class)]
final class RulesToTypeScriptTest extends TestCase
{
    #[Test]
    public function it_maps_scalars_optionality_and_nullability(): void
    {
        $type = RulesToTypeScript::objectType([
            'id' => ['required', 'ulid'],
            'count' => ['sometimes', 'integer'],
            'note' => ['nullable', 'string', 'max:10'],
            'flag' => 'required|boolean',
            'title' => [new BoundedString(1, 100)],
            'kind' => ['required', Rule::in(['a', 'b'])],
            'mode' => ['required', 'in:x,y'],
            'blob' => ['required'],
        ]);

        $this->assertSame(<<<'TS'
{
  id: string
  count?: number
  note?: string | null
  flag: boolean
  title: string
  kind: 'a' | 'b'
  mode: 'x' | 'y'
  blob: unknown
}
TS, $type);
    }

    #[Test]
    public function it_nests_dotted_keys_and_wildcards(): void
    {
        $type = RulesToTypeScript::objectType([
            'items' => ['required', 'array'],
            'items.*.sku' => ['required', 'string'],
            'items.*.qty' => ['required', 'integer'],
            'address.city' => ['required', 'string'],
            'address.zip' => ['nullable', 'string'],
        ]);

        $this->assertSame(<<<'TS'
{
  items: Array<{
    sku: string
    qty: number
  }>
  address?: {
    city: string
    zip?: string | null
  }
}
TS, $type);
    }

    #[Test]
    public function no_rules_is_an_empty_object(): void
    {
        $this->assertSame('Record<string, never>', RulesToTypeScript::objectType([]));
    }
}
