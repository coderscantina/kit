<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

final class NoDocblockTestAnnotationsTest extends TestCase
{
    /**
     * PHPUnit 13 ignores `@test` docblocks, so a test written that way stops
     * running without any failure. Every test method uses the attribute.
     */
    #[Test]
    public function no_test_uses_the_docblock_annotation(): void
    {
        $offenders = [];

        $finder = Finder::create()
            ->in([dirname(__DIR__), dirname(__DIR__, 2).'/packages/reactive/tests', dirname(__DIR__, 2).'/app'])
            ->name('*Test.php')
            ->files();

        foreach ($finder as $file) {
            if (preg_match('/^\s*\*\s*@test\b/m', $file->getContents()) === 1) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders, 'Replace @test docblocks with #[Test] in: '.implode(', ', $offenders));
    }
}
