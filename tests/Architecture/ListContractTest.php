<?php

declare(strict_types=1);

namespace Tests\Architecture;

use CodersCantina\Filter\AdvancedFilter;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionParameter;
use Tests\TestCase;

/**
 * The list contract's rules that a review would otherwise have to catch
 * (docs/lists.md): every filter states the parameters it accepts and the
 * columns it sorts by, and a table is never put inside a card.
 */
final class ListContractTest extends TestCase
{
    #[Test]
    public function every_filter_whitelists_its_parameters_and_bounds_its_sort(): void
    {
        $filters = glob(app_path('Http/Filters/*Filter.php')) ?: [];
        $this->assertNotEmpty($filters);

        foreach ($filters as $file) {
            $class = 'App\\Http\\Filters\\'.basename($file, '.php');
            $this->assertTrue(is_subclass_of($class, AdvancedFilter::class), "{$class} does not extend AdvancedFilter.");

            $filter = $this->instantiate($class);
            $whitelist = $this->property($filter, 'whitelistedFilters');

            $this->assertNotEmpty($whitelist, "{$class} never calls setWhitelistedFilters(), so the whole query bag reaches apply().");
            $this->assertSame([], array_intersect(['limit', 'offset'], $whitelist), "{$class} lets the client reach the inherited limit/offset helpers.");

            if (in_array('sort', $whitelist, true)) {
                $this->assertNotEmpty($this->property($filter, 'sortableColumns'), "{$class} accepts `sort` with no \$sortableColumns, and the value reaches orderBy().");
            }
        }
    }

    #[Test]
    public function no_table_sits_inside_a_card(): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('js')));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'vue') {
                continue;
            }

            $this->assertFalse(
                $this->tableInsideCard((string) file_get_contents($file->getPathname())),
                str_replace(base_path().'/', '', $file->getPathname()).' puts a table inside a Card. The table draws its own border; see components/ui/table/Table.vue.',
            );
        }
    }

    #[Test]
    public function the_card_check_tells_nesting_from_neighbours(): void
    {
        $this->assertTrue($this->tableInsideCard('<Card class="p-4"><Table><TableRow /></Table></Card>'));
        $this->assertTrue($this->tableInsideCard("<Card>\n  <div>\n    <table>"));
        $this->assertFalse($this->tableInsideCard('<Card><CardHeader /></Card><Table><TableRow /></Table>'));
        $this->assertFalse($this->tableInsideCard('<Card><TableRow /></Card>'));
    }

    private function tableInsideCard(string $source): bool
    {
        preg_match_all('/<Card(?=[\s>\/])|<\/Card>|<Table(?=[\s>\/])|<table(?=[\s>])/', $source, $matches);
        $depth = 0;

        foreach ($matches[0] as $tag) {
            match ($tag) {
                '<Card' => $depth++,
                '</Card>' => $depth = max(0, $depth - 1),
                default => null,
            };

            if ($depth > 0 && ($tag === '<Table' || $tag === '<table')) {
                return true;
            }
        }

        return false;
    }

    /**
     * An empty query bag, and a zero value for any constructor argument a
     * filter adds after it (PeopleFilter takes the column it searches).
     *
     * @param  class-string<AdvancedFilter>  $class
     */
    private function instantiate(string $class): AdvancedFilter
    {
        $parameters = (new ReflectionClass($class))->getConstructor()?->getParameters() ?? [];

        return new $class(...array_map(fn (ReflectionParameter $parameter): mixed => match ((string) $parameter->getType()) {
            'array' => [],
            'int' => 0,
            'bool' => false,
            default => '',
        }, $parameters));
    }

    /**
     * @return list<string>
     */
    private function property(object $filter, string $name): array
    {
        $value = (new ReflectionClass($filter))->getProperty($name)->getValue($filter);

        return is_array($value) ? array_values(array_map(strval(...), $value)) : [];
    }
}
