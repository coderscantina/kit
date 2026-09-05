<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * One naming rule, shared by every generator, so `make:feature Post` and
 * `make:query posts.list` agree on where the files go.
 *
 * `Post` / `posts` / `Posts` all resolve to the feature `Post`, whose model
 * is `Post`, table `posts`, ability prefix `posts` and page directory
 * `resources/js/pages/posts`.
 */
trait ResolvesFeatureNames
{
    /**
     * @return array{feature: string, table: string, resource: string, page: string, title: string}
     */
    protected function featureNames(string $input): array
    {
        $feature = Str::studly(Str::singular(trim($input)));
        $plural = Str::pluralStudly($feature);

        return [
            'feature' => $feature,
            'table' => Str::snake($plural),
            'resource' => Str::snake($plural),
            'page' => Str::kebab($plural),
            'title' => Str::headline($plural),
        ];
    }

    /**
     * Resolve the `feature` half of a `feature.name` argument against the
     * directories that exist, so a missing feature fails with the list
     * instead of writing a query into a namespace nobody loads.
     *
     * @return array{feature: string, table: string, resource: string, page: string, title: string}
     */
    protected function existingFeature(string $segment): array
    {
        $names = $this->featureNames($segment);

        if (! is_dir(app_path('Features/'.$names['feature']))) {
            throw new RuntimeException(
                "No feature '{$names['feature']}' in app/Features. Run `php artisan make:feature {$names['feature']}` first"
                    .($this->existingFeatures() === [] ? '.' : '; existing: '.implode(', ', $this->existingFeatures()).'.')
            );
        }

        return $names;
    }

    /**
     * Split `posts.list` into its two halves.
     *
     * @return array{0: string, 1: string}
     */
    protected function splitReactiveName(string $name): array
    {
        $parts = explode('.', trim($name));

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new RuntimeException("Expected a `feature.name` argument such as `posts.list`, got '{$name}'.");
        }

        return [$parts[0], $parts[1]];
    }

    /**
     * @return array<int, string>
     */
    private function existingFeatures(): array
    {
        $directories = glob(app_path('Features/*'), GLOB_ONLYDIR) ?: [];

        return array_map(basename(...), $directories);
    }
}
