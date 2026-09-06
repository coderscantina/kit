<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Data\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreAvatarRequest;
use App\Models\User;
use App\Services\Account\AvatarStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Avatars are read back through the app rather than from a public bucket URL,
 * so a members-only installation stays members-only. See docs/account.md.
 */
class AvatarController extends Controller
{
    public function __construct(
        private readonly AvatarStorage $avatars,
    ) {}

    /**
     * Setting the avatar replaces the account's single one, so this answers
     * 200 with the updated account rather than 201 with a new resource.
     */
    public function store(StoreAvatarRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->avatars->store($user, $request->file('avatar'));

        return response()->json(UserData::fromModel($user->load(['role', 'emailChange'])));
    }

    public function destroy(Request $request): UserData
    {
        /** @var User $user */
        $user = $request->user();

        $this->avatars->remove($user);

        return UserData::fromModel($user->load(['role', 'emailChange']));
    }

    /**
     * The URL carries a version derived from the stored path, so the response
     * is immutable and a replaced avatar is simply a different URL.
     */
    public function show(User $user): Response
    {
        $contents = $this->avatars->read($user);

        abort_if($contents === null, 404);

        return response($contents, 200, [
            'Content-Type' => $this->avatars->mimeType($user),
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
