<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Services\Auth\AuthorizationService;

/**
 * One ability for all of it. Seeing an endpoint's deliveries shows what the
 * app sends out, which is as sensitive as deciding where it goes.
 */
class WebhookEndpointPolicy
{
    public function __construct(
        private readonly AuthorizationService $authorization,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->can($user, 'webhooks.manage');
    }

    public function create(User $user): bool
    {
        return $this->authorization->can($user, 'webhooks.manage');
    }

    public function update(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->authorization->can($user, 'webhooks.manage');
    }

    public function delete(User $user, WebhookEndpoint $endpoint): bool
    {
        return $this->authorization->can($user, 'webhooks.manage');
    }
}
