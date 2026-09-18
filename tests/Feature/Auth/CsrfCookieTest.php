<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The SPA primes its XSRF cookie at `/auth/csrf-cookie`
 * (resources/js/api/client.ts). Sanctum's default is `/sanctum`, so this
 * pins the route to where the client calls it.
 */
final class CsrfCookieTest extends TestCase
{
    #[Test]
    public function the_cookie_route_answers_where_the_client_asks(): void
    {
        $this->get('/auth/csrf-cookie')
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN');
    }
}
