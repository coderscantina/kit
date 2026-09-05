<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\Console\Commands\Concerns\WritesStubs;
use Illuminate\Console\Command;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

#[CoversTrait(WritesStubs::class)]
final class WritesStubsTest extends TestCase
{
    private string $file = '';

    protected function tearDown(): void
    {
        if ($this->file !== '' && is_file($this->file)) {
            unlink($this->file);
        }

        parent::tearDown();
    }

    #[Test]
    public function setting_a_nested_key_leaves_a_sibling_empty_object_an_object(): void
    {
        // `{}` and `[]` both decode to `[]` with assoc on, so a naive round
        // trip rewrote the empty group as a JSON array and vue-i18n stopped
        // seeing a group at all.
        $this->file = $this->messages(['nav' => ['dashboard' => 'Dashboard'], 'mutations' => new stdClass]);

        $this->writeKey('nav.posts', 'Posts');

        $written = file_get_contents($this->file) ?: '';

        $this->assertStringContainsString('"mutations": {}', $written);
        $this->assertStringNotContainsString('"mutations": []', $written);
        $this->assertSame(
            ['nav' => ['dashboard' => 'Dashboard', 'posts' => 'Posts'], 'mutations' => []],
            json_decode($written, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function it_creates_the_missing_groups_on_the_way_to_the_leaf(): void
    {
        // What make:mutation writes: none of `mutations.posts.create` exists.
        $this->file = $this->messages(['mutations' => new stdClass]);

        $this->writeKey('mutations.posts.create.error', 'Something went wrong.');

        $this->assertSame(
            ['mutations' => ['posts' => ['create' => ['error' => 'Something went wrong.']]]],
            json_decode(file_get_contents($this->file) ?: '', true, 512, JSON_THROW_ON_ERROR),
        );
    }

    #[Test]
    public function an_existing_key_is_left_alone(): void
    {
        $this->file = $this->messages(['nav' => ['posts' => 'Beiträge']]);

        $this->writeKey('nav.posts', 'Posts');

        $this->assertSame(
            ['nav' => ['posts' => 'Beiträge']],
            json_decode(file_get_contents($this->file) ?: '', true, 512, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @param  array<string, mixed>  $tree
     */
    private function messages(array $tree): string
    {
        $path = tempnam(sys_get_temp_dir(), 'kit-messages').'.json';
        file_put_contents($path, json_encode($tree, JSON_THROW_ON_ERROR));

        return $path;
    }

    /**
     * The trait needs a Command for `$this->components`, so drive a throwaway
     * one rather than reaching into the trait directly.
     */
    private function writeKey(string $dotted, string $value): void
    {
        $command = new class($this->file, $dotted, $value) extends Command
        {
            use WritesStubs;

            protected $signature = 'kit:test-write-json';

            public function __construct(
                private readonly string $file,
                private readonly string $dotted,
                private readonly string $value,
            ) {
                parent::__construct();
            }

            public function handle(): int
            {
                $this->setJsonKey($this->file, $this->dotted, $this->value);

                return self::SUCCESS;
            }
        };

        $command->setLaravel($this->app);
        $command->run(new ArrayInput([]), new NullOutput);
    }
}
