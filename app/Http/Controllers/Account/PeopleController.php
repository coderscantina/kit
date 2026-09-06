<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Data\PersonData;
use App\Http\Controllers\Controller;
use App\Models\Invite;
use App\Models\User;
use App\Services\Account\PeopleDirectory;
use Illuminate\Http\Request;

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
        /** @var User $viewer */
        $viewer = $request->user();

        abort_unless(
            $viewer->can('viewAny', User::class) || $viewer->can('viewAny', Invite::class),
            403,
        );

        $result = $directory->paginate($viewer, [
            'search' => $request->string('search')->toString(),
            'role' => $request->string('role')->toString(),
            'status' => $request->string('status')->toString(),
            'sort' => $request->string('sort')->toString(),
            'direction' => $request->string('direction')->toString(),
        ], $this->perPage($request));

        // Same envelope as the other list endpoints, plus the tab counts.
        return [
            ...PersonData::collect($result['paginator']->withQueryString())->toArray(),
            'counts' => $result['counts'],
        ];
    }
}
