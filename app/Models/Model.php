<?php

declare(strict_types=1);

namespace App\Models;

use CodersCantina\Filter\Filterable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Kit\Reactive\Invalidation\HasReactiveInvalidation;

/**
 * Base for every model in the app. ULID keys (sortable, URL-safe, no
 * autoincrement leaks) and reactive invalidation, so a save anywhere reaches
 * the subscriptions that depend on the row. `->filter($filter)` is the list
 * contract: filters and sorting come from an App\Http\Filters class, never
 * from request values spliced into the builder. Strictness is switched on for
 * all environments in AppServiceProvider.
 */
abstract class Model extends Eloquent
{
    use Filterable;
    use HasReactiveInvalidation;
    use HasUlids;
}
