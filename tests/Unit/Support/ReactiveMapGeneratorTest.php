<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use Kit\Reactive\TypeScript\ReactiveMapGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Reactive\ReactiveFixtures;
use Tests\TestCase;

#[CoversClass(ReactiveMapGenerator::class)]
final class ReactiveMapGeneratorTest extends TestCase
{
    #[Test]
    public function it_emits_args_from_rules_and_results_from_attributes_or_return_types(): void
    {
        ReactiveFixtures::install();

        $map = app(ReactiveMapGenerator::class)->generate();

        $this->assertStringContainsString("'notes.list': {\n      args: {\n        ownerId: string\n      }\n      result: Array<Tests.Fixtures.Reactive.NoteData>", $map);
        $this->assertStringContainsString("'notes.rename': {\n      args: {\n        id: string\n        title: string\n      }\n      result: Tests.Fixtures.Reactive.NoteData", $map);
        $this->assertStringContainsString("'notes.fail': {\n      args: Record<string, never>\n      result: unknown", $map);
        $this->assertStringStartsWith('declare namespace Kit {', $map);
    }
}
