<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Descriptor\TextDescriptor;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Writes each documented command's `--help` output into docs/generated, so a
 * doc page embeds the real signature instead of a copy that drifts. Runs
 * before `vitepress dev` and `vitepress build`; the output is not committed.
 */
class DocsCommandsCommand extends Command
{
    /**
     * The commands the docs describe. Adding one here and a
     * `<!--@include: ./generated/<slug>.md-->` to a page is the whole job.
     */
    private const array COMMANDS = [
        'kit:setup',
        'kit:doctor',
        'config:validate',
        'make:feature',
        'make:query',
        'make:mutation',
        'make:ai-action',
        'make:filter',
        'make:endpoint',
        'make:data',
        'make:job',
        'types:generate',
        'ai:models',
        'reactive:gc',
        'reactive:cache',
        'reactive:clear',
        'reactive:inspect',
    ];

    protected $signature = 'docs:commands';

    protected $description = 'Write the documented commands\' --help output into docs/generated';

    public function handle(Filesystem $files): int
    {
        $directory = base_path('docs/generated');
        $files->ensureDirectoryExists($directory);

        $application = $this->getApplication();

        if ($application === null) {
            $this->components->error('No console application; run this through artisan.');

            return self::FAILURE;
        }

        foreach (self::COMMANDS as $name) {
            $buffer = new BufferedOutput;
            (new TextDescriptor)->describe($buffer, $application->find($name));

            $slug = str_replace(':', '-', $name);
            $files->put("{$directory}/{$slug}.md", "```\n".trim($buffer->fetch())."\n```\n");
        }

        $this->components->info(count(self::COMMANDS).' command(s) written to docs/generated.');

        return self::SUCCESS;
    }
}
