<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Data\MeData;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\ImpersonationService;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request, ImpersonationService $impersonation): MeData
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['role', 'emailChange']);

        return MeData::fromUser($user, $impersonation->isImpersonating($request->session()));
    }
}
