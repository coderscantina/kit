<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\SavedView;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A saved list state as the client sees it. `params` is the query bag to put
 * back into the URL, so applying a view is a navigation and nothing more.
 */
#[TypeScript]
class SavedViewData extends Data
{
    /**
     * @param  array<string, string>  $params
     */
    public function __construct(
        public string $id,
        public string $scope,
        public string $name,
        public array $params,
        public bool $isDefault,
        public string $createdAt,
    ) {}

    public static function fromModel(SavedView $view): self
    {
        return new self(
            id: $view->id,
            scope: $view->scope,
            name: $view->name,
            params: $view->params,
            isDefault: $view->is_default,
            createdAt: $view->created_at?->toIso8601String() ?? '',
        );
    }
}
