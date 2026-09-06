<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Data\SecurityEventData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The account's own security trail. Always scoped to the caller: there is no
 * route here that reads another account's history.
 */
class SecurityActivityController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();

        $events = $user->securityEvents()
            // ULIDs sort by creation time, and unlike created_at they are unique.
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return SecurityEventData::collect($events)->toArray();
    }
}
