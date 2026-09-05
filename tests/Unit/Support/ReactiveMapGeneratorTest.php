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
    public function it_names_the_args_class_and_takes_results_from_attributes_or_return_types(): void
    {
        ReactiveFixtures::install();

        $map = app(ReactiveMapGenerator::class)->generate();

        $this->assertStringContainsString("'notes.list': {\n      args: Tests.Fixtures.Reactive.ListNotesArgs\n      result: Array<Tests.Fixtures.Reactive.NoteData>", $map);
        $this->assertStringContainsString("'notes.rename': {\n      args: Tests.Fixtures.Reactive.RenameNoteArgs\n      result: Tests.Fixtures.Reactive.NoteData", $map);
        // NoArgs is the one args class that is not written out as a type.
        $this->assertStringContainsString("'notes.fail': {\n      args: Record<string, never>\n      result: unknown", $map);
        $this->assertStringStartsWith('declare namespace Kit {', $map);
    }
}
