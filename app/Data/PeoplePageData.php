<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One page of the people list and the tab counts. The counters are snake case
 * on purpose: they are the paginator envelope every list footer already reads.
 */
#[TypeScript]
final class PeoplePageData extends Data
{
    /**
     * @param  array<int, PersonData>  $data
     * @param  array{active: int, pending: int, total: int}  $counts
     */
    public function __construct(
        #[DataCollectionOf(PersonData::class)]
        public array $data,
        public int $current_page,
        public int $last_page,
        public int $per_page,
        public int $total,
        public ?int $from,
        public ?int $to,
        public array $counts,
    ) {}

    /**
     * @param  LengthAwarePaginator<int, PersonData>  $page
     * @param  array{active: int, pending: int, total: int}  $counts
     */
    public static function fromPaginator(LengthAwarePaginator $page, array $counts): self
    {
        return new self(
            data: array_values($page->items()),
            current_page: $page->currentPage(),
            last_page: $page->lastPage(),
            per_page: $page->perPage(),
            total: $page->total(),
            from: $page->firstItem(),
            to: $page->lastItem(),
            counts: $counts,
        );
    }
}
