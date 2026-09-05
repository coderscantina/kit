<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Data\MeData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ImpersonateRequest;
use App\Models\User;
use App\Services\Auth\ImpersonationService;
use App\Support\FeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function __construct(
        private readonly ImpersonationService $impersonation,
    ) {}

    public function store(ImpersonateRequest $request): JsonResponse
    {
        abort_unless(FeatureGate::impersonationEnabled(), 404);

        /** @var User $actor */
        $actor = $request->user();
        $target = User::query()->findOrFail($request->string('userId')->toString());

        $this->authorize('impersonate', $target);

        $this->impersonation->start($request->session(), $actor, $target);

        return response()->json(MeData::fromUser($target, true));
    }

    public function destroy(Request $request): JsonResponse
    {
        $actor = $this->impersonation->stop($request->session());

        if ($actor === null) {
            return response()->json(['message' => __('auth.not_impersonating')], 409);
        }

        return response()->json(MeData::fromUser($actor, false));
    }
}
