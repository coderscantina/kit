<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Data\SavedViewData;
use App\Http\Controllers\Controller;
use App\Http\Requests\SavedViews\StoreSavedViewRequest;
use App\Http\Requests\SavedViews\UpdateSavedViewRequest;
use App\Models\SavedView;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * The saved filter/sort states of the signed-in user. Private by definition:
 * every query is scoped to the owner, so there is no policy to forget.
 */
class SavedViewController extends Controller
{
    /** Enough for a working set, few enough that the menu stays a menu. */
    private const MAX_PER_SCOPE = 30;

    /**
     * @return array<int, SavedViewData>
     */
    public function index(Request $request): array
    {
        return $this->scoped($request)
            ->when(
                $request->filled('scope'),
                fn ($query) => $query->where('scope', $request->string('scope')->toString())
            )
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(SavedViewData::fromModel(...))
            ->all();
    }

    public function store(StoreSavedViewRequest $request): SavedViewData
    {
        /** @var User $user */
        $user = $request->user();
        $scope = $request->string('scope')->toString();

        abort_if(
            $this->scoped($request)->where('scope', $scope)->count() >= self::MAX_PER_SCOPE,
            422,
            __('views.limit_reached'),
        );

        $view = DB::transaction(function () use ($request, $user, $scope): SavedView {
            $view = $user->savedViews()->updateOrCreate(
                ['scope' => $scope, 'name' => $request->string('name')->toString()],
                [
                    'params' => $this->params($request),
                    'is_default' => $request->boolean('isDefault'),
                ],
            );

            $this->settleDefault($user, $view);

            return $view;
        });

        return SavedViewData::fromModel($view);
    }

    public function update(UpdateSavedViewRequest $request, SavedView $savedView): SavedViewData
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($savedView->user_id === $user->id, 404);

        DB::transaction(function () use ($request, $user, $savedView): void {
            $savedView->fill(array_filter([
                'name' => $request->has('name') ? $request->string('name')->toString() : null,
                'params' => $request->has('params') ? $this->params($request) : null,
            ], fn (mixed $value): bool => $value !== null));

            if ($request->has('isDefault')) {
                $savedView->is_default = $request->boolean('isDefault');
            }

            $savedView->save();

            $this->settleDefault($user, $savedView);
        });

        return SavedViewData::fromModel($savedView);
    }

    public function destroy(Request $request, SavedView $savedView): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($savedView->user_id === $user->id, 404);

        $savedView->delete();

        return response()->noContent();
    }

    /**
     * One default per scope. Clearing the others here rather than in the
     * client keeps two tabs from both believing they hold it.
     */
    private function settleDefault(User $user, SavedView $view): void
    {
        if (! $view->is_default) {
            return;
        }

        $user->savedViews()
            ->where('scope', $view->scope)
            ->whereKeyNot($view->getKey())
            ->update(['is_default' => false]);
    }

    /**
     * @return array<string, string>
     */
    private function params(Request $request): array
    {
        /** @var array<string, string|null> $params */
        $params = $request->array('params');

        // A null value means "not set"; storing it would put an empty
        // parameter back into the URL when the view is applied.
        return array_filter(
            array_map(strval(...), array_filter($params, fn (mixed $value): bool => $value !== null && $value !== '')),
        );
    }

    /**
     * @return Builder<SavedView>
     */
    private function scoped(Request $request): Builder
    {
        /** @var User $user */
        $user = $request->user();

        return $user->savedViews()->getQuery();
    }
}
