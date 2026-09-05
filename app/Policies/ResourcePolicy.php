<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Services\Auth\AuthorizationService;

/**
 * Base policy for resources whose abilities follow the standard
 * `<resource>.view` / `<resource>.manage` mapping with no per-model rules.
 * Concrete policies only declare `$resource`.
 *
 * Do not extend this when a policy has real per-model logic (ownership,
 * state checks). Inheriting create() or update() from here would flip a
 * denial into an allow; write the methods out instead.
 */
abstract class ResourcePolicy
{
    protected string $resource;

    public function viewAny(User $user): bool
    {
        return $this->can($user, "{$this->resource}.view");
    }

    public function view(User $user, mixed $model): bool
    {
        return $this->can($user, "{$this->resource}.view");
    }

    public function create(User $user): bool
    {
        return $this->can($user, "{$this->resource}.manage");
    }

    public function update(User $user, mixed $model): bool
    {
        return $this->can($user, "{$this->resource}.manage");
    }

    public function delete(User $user, mixed $model): bool
    {
        return $this->can($user, "{$this->resource}.manage");
    }

    protected function can(User $user, string $ability): bool
    {
        return app(AuthorizationService::class)->can($user, $ability);
    }
}
