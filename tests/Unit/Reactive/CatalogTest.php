<?php

declare(strict_types=1);

namespace Tests\Unit\Reactive;

use Illuminate\Support\Facades\File;
use Kit\Reactive\Registry\Catalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Reactive\ListNotes;
use Tests\Fixtures\Reactive\RenameNote;
use Tests\TestCase;

/**
 * The discovery directory is a temporary one holding files named after the
 * fixture classes: the catalog derives the class name from the path and
 * autoloads the real class, so adding a file mid-test is a deploy adding a
 * query to a process that is already running.
 */
#[CoversClass(Catalog::class)]
final class CatalogTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = base_path('storage/framework/testing/catalog-'.uniqid());
        File::ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    #[Test]
    public function a_query_added_after_the_map_was_built_is_found_rather_than_reported_missing(): void
    {
        $catalog = $this->catalog();

        $this->assertNull($catalog->query('notes.list'));

        File::put($this->directory.'/ListNotes.php', '');

        $this->assertSame(ListNotes::class, $catalog->query('notes.list'));
    }

    #[Test]
    public function a_mutation_added_after_the_map_was_built_is_found_too(): void
    {
        $catalog = $this->catalog();

        $this->assertNull($catalog->mutation('notes.rename'));

        File::put($this->directory.'/RenameNote.php', '');

        $this->assertSame(RenameNote::class, $catalog->mutation('notes.rename'));
    }

    #[Test]
    public function a_name_that_was_never_real_is_answered_from_the_map_rather_than_the_disk(): void
    {
        $catalog = $this->catalog(rescanSeconds: 60.0);

        $this->assertNull($catalog->query('notes.list'));

        File::put($this->directory.'/ListNotes.php', '');

        $this->assertNull($catalog->query('notes.list'));
    }

    private function catalog(float $rescanSeconds = 0.0): Catalog
    {
        return new Catalog(['Tests\\Fixtures\\Reactive' => $this->directory], [], null, $rescanSeconds);
    }
}
