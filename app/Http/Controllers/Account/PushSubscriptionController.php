<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StorePushSubscriptionRequest;
use App\Models\User;
use App\Notifications\TestPushNotification;
use App\Support\FeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The browsers this account has allowed to receive push notifications.
 *
 * One row per browser, keyed by the endpoint the push service issued. A
 * browser that re-subscribes gets the same row back, so switching devices
 * never accumulates dead endpoints for the same person.
 *
 * @see docs/push-notifications.md
 */
class PushSubscriptionController extends Controller
{
    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        abort_unless(FeatureGate::pushEnabled(), 404);

        /** @var User $user */
        $user = $request->user();

        $user->updatePushSubscription(
            $request->string('endpoint')->toString(),
            $request->string('keys.p256dh')->toString(),
            $request->string('keys.auth')->toString(),
            $request->string('contentEncoding')->toString() ?: null,
        );

        return response()->json(['subscribed' => true], 201);
    }

    public function destroy(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $endpoint = $request->string('endpoint')->toString();

        // No endpoint means "this account, everywhere": what the account page
        // offers when the switch is turned off from a browser that has lost
        // its own subscription.
        $endpoint === ''
            ? $user->pushSubscriptions()->delete()
            : $user->deletePushSubscription($endpoint);

        return response()->noContent();
    }

    /**
     * Proves the round trip: permission granted, service worker registered,
     * endpoint stored, notification delivered. Without it the switch is a
     * promise nobody can check.
     */
    public function test(Request $request): JsonResponse
    {
        abort_unless(FeatureGate::pushEnabled(), 404);

        /** @var User $user */
        $user = $request->user();

        abort_if($user->pushSubscriptions()->count() === 0, 422, __('push.not_subscribed'));

        $user->notify(new TestPushNotification);

        return response()->json(['sent' => true]);
    }
}
