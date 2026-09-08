<?php

declare(strict_types=1);

namespace App\Services\Sms;

/**
 * One text message: where it goes and what it says.
 *
 * No subject, no formatting, no attachments. An SMS is 160 characters that
 * either say the thing or waste the send, so the value object refuses to
 * pretend otherwise.
 */
final class SmsMessage
{
    public function __construct(
        /** E.164, the only format a gateway accepts. */
        public readonly string $to,
        public readonly string $content,
    ) {}

    public function to(string $to): self
    {
        return new self($to, $this->content);
    }
}
