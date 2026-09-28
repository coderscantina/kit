<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Exceptions\UnsafeUrlException;
use App\Services\Security\OutboundUrlGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(OutboundUrlGuard::class)]
final class OutboundUrlGuardTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function internal(): array
    {
        return [
            'loopback' => ['http://127.0.0.1/hook'],
            'metadata' => ['http://169.254.169.254/latest/meta-data'],
            'private' => ['https://10.0.0.5/hook'],
            'carrier-grade NAT' => ['http://100.100.100.200/'],
            'IPv6 loopback' => ['http://[::1]/hook'],
            'IPv4-mapped metadata' => ['http://[::ffff:169.254.169.254]/'],
            'not http' => ['file:///etc/passwd'],
        ];
    }

    #[Test]
    #[DataProvider('internal')]
    public function an_internal_or_non_http_target_is_refused(string $url): void
    {
        $this->expectException(UnsafeUrlException::class);

        app(OutboundUrlGuard::class)->assertSafe($url);
    }

    #[Test]
    public function a_public_address_passes_and_is_pinned(): void
    {
        $guard = app(OutboundUrlGuard::class);

        $this->assertSame(['93.184.215.14'], $guard->assertSafe('https://93.184.215.14/hook'));
        $this->assertSame(['hooks.example.com:443:93.184.215.14'], $guard->curlResolveFor('https://hooks.example.com/x', ['93.184.215.14']));
    }

    #[Test]
    public function the_local_switch_lets_a_private_target_through(): void
    {
        config(['kit.webhooks.allow_private_targets' => true]);

        $this->assertSame(['127.0.0.1'], app(OutboundUrlGuard::class)->assertSafe('http://127.0.0.1:9000/hook'));
    }
}
