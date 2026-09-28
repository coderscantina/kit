<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Data\AccessTokenData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreAccessTokenRequest;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Account\SecurityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Personal access tokens. A token opens the MCP endpoint and nothing else
 * (see AppServiceProvider), carries a subset of its owner's abilities, and
 * is shown once: only its hash is stored.
 */
class AccessTokenController extends Controller
{
    public function __construct(
        private readonly SecurityLog $securityLog,
    ) {}

    /**
     * @return array<int, AccessTokenData>
     */
    public function index(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();

        return $user->tokens()
            ->latest('id')
            ->get()
            ->map(fn (PersonalAccessToken $token): AccessTokenData => AccessTokenData::fromModel($token))
            ->all();
    }

    public function store(StoreAccessTokenRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $days = $request->integer('expires_in_days') ?: null;

        // Every authenticated route checks `app.access`, so a token without
        // it could not reach the endpoint it exists for.
        /** @var array<int, string> $abilities */
        $abilities = array_values(array_unique(['app.access', ...$request->input('abilities')]));

        $created = $user->createToken(
            $request->string('name')->toString(),
            $abilities,
            $days === null ? null : now()->addDays($days),
        );

        $this->securityLog->record($user, SecurityEvent::TOKEN_CREATED, ['name' => $created->accessToken->name]);

        return response()->json([
            'token' => AccessTokenData::fromModel($created->accessToken),
            'plainTextToken' => $created->plainTextToken,
        ], 201);
    }

    public function destroy(Request $request, string $token): Response
    {
        /** @var User $user */
        $user = $request->user();

        $record = $user->tokens()->whereKey($token)->first();
        abort_if($record === null, 404);

        $record->delete();
        $this->securityLog->record($user, SecurityEvent::TOKEN_REVOKED, ['name' => $record->name]);

        return response()->noContent();
    }
}
