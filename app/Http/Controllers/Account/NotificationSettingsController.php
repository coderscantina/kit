<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Actions\Notifications\UpdateNotificationPreferences;
use App\Data\NotificationSettingsData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdateNotificationSettingsRequest;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The preferences matrix: which notifications reach this account, and where.
 *
 * REST rather than a reactive query, because it is a form the browser posts
 * and reads back once. The inbox next door is the live surface; a settings
 * screen has no second writer to race with.
 *
 * The payload is built from the #[NotificationType] classes the app actually
 * ships, so the screen cannot offer a switch for something that no longer
 * exists or miss one that was just added.
 *
 * @see docs/notifications.md
 */
class NotificationSettingsController extends Controller
{
    public function index(Request $request): NotificationSettingsData
    {
        /** @var User $user */
        $user = $request->user();

        return NotificationSettingsData::forUser($user);
    }

    public function update(UpdateNotificationSettingsRequest $request, UpdateNotificationPreferences $update): NotificationSettingsData
    {
        /** @var User $user */
        $user = $request->user();

        /** @var array<string, array<int, string>> $preferences */
        $preferences = $request->array('preferences');

        $update->execute($user, $preferences);

        return NotificationSettingsData::forUser($user);
    }
}
