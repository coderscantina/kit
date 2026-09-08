<?php

declare(strict_types=1);

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Writes the message to the log instead of sending it.
 *
 * The default, and the reason the phone verification flow works on a laptop
 * with no provider account: the code appears in `storage/logs/laravel.log`
 * and the rest of the flow is real.
 *
 * The number is logged in full because a developer reading their own log
 * needs to see which handset they are pretending to text. Do not make this
 * the driver in production.
 */
final class LogSmsSender implements SmsSender
{
    public function __construct(private readonly ?string $channel = null) {}

    public function send(SmsMessage $message): void
    {
        Log::channel($this->channel ?? config('logging.default'))
            ->info('SMS', ['to' => $message->to, 'content' => $message->content]);
    }
}
