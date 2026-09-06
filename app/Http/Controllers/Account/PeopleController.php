<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Data\PersonData;
use App\Http\Controllers\Controller;
use App\Models\Invite;
use App\Models\Role;
use App\Models\User;
use App\Services\Account\PeopleDirectory;
use App\Support\Export\ExportFormat;
use App\Support\Export\ListExport;
use Generator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Accounts and outstanding invitations as one list. Both halves are gated
 * separately, so someone who may see invitations but not accounts gets the
 * invitations and nothing else, rather than a 403.
 */
class PeopleController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function index(Request $request, PeopleDirectory $directory): array
    {
        $viewer = $this->viewer($request);

        // The whole query bag, filtered down by PeopleFilter's own allow list
        // rather than transcribed parameter by parameter here: a new filter is
        // then one method on the filter class, not an edit in three files.
        $result = $directory->paginate($viewer, $request->query(), $this->perPage($request));

        // Same envelope as the other list endpoints, plus the tab counts.
        return [
            ...PersonData::collect($result['paginator']->withQueryString())->toArray(),
            'counts' => $result['counts'],
        ];
    }

    /**
     * The current result set as a file: same filters, same sort, no paging.
     *
     * It runs through the same PeopleDirectory query the table does, so an
     * export cannot drift from the rows it was started from, and it is gated
     * by the same check as the list.
     */
    public function export(Request $request, PeopleDirectory $directory): StreamedResponse
    {
        $viewer = $this->viewer($request);

        $request->validate(['format' => ['required', Rule::enum(ExportFormat::class)]]);

        $format = ExportFormat::from($request->string('format')->toString());
        $result = $directory->export($viewer, $request->query(), $format->rowLimit());

        $columns = (array) __('exports.people.columns');

        return ListExport::stream(
            format: $format,
            basename: 'people',
            title: __('exports.people.title'),
            headings: array_values(array_map(strval(...), $columns)),
            rows: $this->exportRows($result['rows']),
            // The client warns when the file is short of the list on screen.
            headers: $result['total'] > $format->rowLimit() ? ['X-Export-Truncated' => '1'] : [],
        );
    }

    /**
     * One person per row, in the column order `exports.people.columns` names.
     *
     * @param  iterable<int, PersonData>  $people
     * @return Generator<int, list<string>>
     */
    private function exportRows(iterable $people): Generator
    {
        /** @var array<string, string> $roleNames */
        $roleNames = Role::query()->pluck('name', 'key')->all();

        foreach ($people as $person) {
            yield [
                $person->name ?? '',
                $person->email,
                $person->role === null ? '' : ($roleNames[$person->role] ?? $person->role),
                (string) __('exports.people.states.'.$person->state),
                (string) __('exports.people.kinds.'.$person->kind),
                $person->createdAt,
                $person->expiresAt ?? '',
            ];
        }
    }

    /** The caller, once they have been shown to see at least one half of the list. */
    private function viewer(Request $request): User
    {
        /** @var User $viewer */
        $viewer = $request->user();

        abort_unless(
            $viewer->can('viewAny', User::class) || $viewer->can('viewAny', Invite::class),
            403,
        );

        return $viewer;
    }
}
