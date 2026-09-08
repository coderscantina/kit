<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Actions\Account\ConfirmPhoneVerification;
use App\Actions\Account\RemovePhone;
use App\Actions\Account\RequestPhoneVerification;
use App\Data\PhoneData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StorePhoneRequest;
use App\Http\Requests\Account\VerifyPhoneRequest;
use App\Models\User;
use App\Support\FeatureGate;
use Illuminate\Http\Request;

/**
 * The account's phone number, in the only two steps it can move: a code goes
 * to the number, the code comes back.
 *
 * Nothing writes `users.phone` except the confirmation. A number that has not
 * answered is not a number this app will text, which is what keeps somebody
 * from pointing another person's handset at their own security alerts.
 *
 * @see docs/notifications.md
 */
class PhoneController extends Controller
{
    /** Sends, or resends, the code. Rate-limited as a sensitive action. */
    public function store(StorePhoneRequest $request, RequestPhoneVerification $requestVerification): PhoneData
    {
        abort_unless(FeatureGate::smsEnabled(), 404);

        /** @var User $user */
        $user = $request->user();

        $requestVerification->execute($user, $request->string('phone')->toString());

        return PhoneData::fromModel($user);
    }

    public function verify(VerifyPhoneRequest $request, ConfirmPhoneVerification $confirm): PhoneData
    {
        abort_unless(FeatureGate::smsEnabled(), 404);

        /** @var User $user */
        $user = $request->user();

        return PhoneData::fromModel($confirm->execute($user, $request->string('code')->toString()));
    }

    /** Removing a number is not sensitive: it takes a route away, never adds one. */
    public function destroy(Request $request, RemovePhone $remove): PhoneData
    {
        /** @var User $user */
        $user = $request->user();

        return PhoneData::fromModel($remove->execute($user));
    }
}
