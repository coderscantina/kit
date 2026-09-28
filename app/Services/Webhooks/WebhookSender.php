<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Exceptions\UnsafeUrlException;
use App\Models\WebhookDelivery;
use App\Services\Security\OutboundUrlGuard;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Makes one attempt at one delivery and records how it went. The caller
 * decides about retries: a false return is worth another try, anything the
 * receiver can never accept (an unsafe URL, a disabled endpoint) is marked
 * failed here and returns true, because retrying it cannot help.
 */
class WebhookSender
{
    private const int MAX_RESPONSE_LENGTH = 1000;

    public function __construct(
        private readonly OutboundUrlGuard $guard,
    ) {}

    public function attempt(WebhookDelivery $delivery): bool
    {
        $endpoint = $delivery->endpoint;
        $delivery->attempts++;

        if (! $endpoint->active) {
            return $this->finish($delivery, WebhookDelivery::FAILED, null, 'The endpoint is disabled.');
        }

        // Resolved and checked on every attempt, not once at save time: DNS
        // can move a public name onto an internal address in between.
        try {
            $addresses = $this->guard->assertSafe($endpoint->url);
        } catch (UnsafeUrlException $e) {
            return $this->finish($delivery, WebhookDelivery::FAILED, null, $e->getMessage());
        }

        $body = (string) json_encode($delivery->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            $response = Http::withOptions([
                'allow_redirects' => false,
                'curl' => [CURLOPT_RESOLVE => $this->guard->curlResolveFor($endpoint->url, $addresses)],
            ])
                ->connectTimeout(5)
                ->timeout((int) config('kit.webhooks.timeout_seconds', 10))
                ->withUserAgent(config('app.name').' Webhooks')
                ->withHeaders($endpoint->signatureHeaders($delivery->id, now()->getTimestamp(), $body))
                ->withBody($body, 'application/json')
                ->post($endpoint->url);
        } catch (ConnectionException $e) {
            $this->record($delivery, null, $e->getMessage());

            return false;
        }

        if ($response->successful()) {
            return $this->finish($delivery, WebhookDelivery::SUCCEEDED, $response->status(), $response->body());
        }

        $this->record($delivery, $response->status(), $response->body());

        return false;
    }

    /** The last attempt failed and there will be no other. */
    public function giveUp(WebhookDelivery $delivery): void
    {
        $delivery->status = WebhookDelivery::FAILED;
        $delivery->save();
    }

    private function finish(WebhookDelivery $delivery, string $status, ?int $code, string $body): bool
    {
        $delivery->status = $status;
        $delivery->delivered_at = $status === WebhookDelivery::SUCCEEDED ? now() : null;
        $this->record($delivery, $code, $body);

        return true;
    }

    private function record(WebhookDelivery $delivery, ?int $code, string $body): void
    {
        $delivery->response_status = $code;
        $delivery->response_body = $body === '' ? null : Str::limit($body, self::MAX_RESPONSE_LENGTH);
        $delivery->save();
    }
}
