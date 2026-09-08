<?php

declare(strict_types=1);

namespace App\Services\Sms;

/**
 * The seam a provider plugs into.
 *
 * The kit ships LogSmsSender and nothing else: an SMS gateway is an account,
 * a contract and a bill, and guessing which one an installation wants would
 * be a dependency nobody asked for. Bind your own implementation in a service
 * provider and name it in config/sms.php.
 *
 * An implementation throws on failure. The caller is a queued notification,
 * so a throw is a retry with backoff rather than a lost message.
 */
interface SmsSender
{
    public function send(SmsMessage $message): void;
}
