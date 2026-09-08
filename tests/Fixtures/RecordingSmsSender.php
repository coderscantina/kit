<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Services\Sms\SmsMessage;
use App\Services\Sms\SmsSender;

/**
 * Keeps every message instead of sending it, so a test can read what a
 * notification would have texted. Bound in place of the configured sender.
 */
final class RecordingSmsSender implements SmsSender
{
    /** @var array<int, SmsMessage> */
    public array $sent = [];

    public function send(SmsMessage $message): void
    {
        $this->sent[] = $message;
    }

    /** The one code a verification test is waiting for. */
    public function lastCode(): string
    {
        preg_match('/(\d{4,10})/', $this->sent[count($this->sent) - 1]->content, $matches);

        return $matches[1] ?? '';
    }
}
