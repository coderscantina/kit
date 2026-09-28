<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\Webhooks\WebhookSender;
use Throwable;

/**
 * Posts one delivery. A receiver that answers non-2xx or does not answer is
 * tried again on a widening schedule, about three hours end to end; the
 * delivery row, not the failed-jobs table, is where that history lives.
 */
class DeliverWebhook extends QueuedJob
{
    public int $tries = 6;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300, 1800, 7200];

    public function __construct(
        public readonly string $deliveryId,
    ) {
        $this->onQueue('default');
    }

    protected function execute(): void
    {
        $delivery = WebhookDelivery::query()->with('endpoint')->find($this->deliveryId);

        if ($delivery === null || $delivery->status !== WebhookDelivery::PENDING) {
            return;
        }

        $sender = app(WebhookSender::class);

        if ($sender->attempt($delivery)) {
            return;
        }

        $attempt = $this->attempts();

        if ($attempt >= $this->tries) {
            $sender->giveUp($delivery);

            return;
        }

        // A release rather than a throw: a receiver being down is expected,
        // and an exception per attempt would fill the error log with it.
        $this->release($this->backoff[$attempt - 1] ?? end($this->backoff));
    }

    protected function handleFailure(Throwable $e): void
    {
        $delivery = WebhookDelivery::query()->find($this->deliveryId);

        if ($delivery?->status === WebhookDelivery::PENDING) {
            app(WebhookSender::class)->giveUp($delivery);
        }

        report($e);
    }
}
