<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Http\IpUtils;
use PHPUnit\Framework\TestCase;

final class IpUtilsTest extends TestCase
{
    public function testCidr(): void
    {
        self::assertTrue(IpUtils::inCidr('10.1.2.3', '10.0.0.0/8'));
        self::assertFalse(IpUtils::inCidr('11.1.2.3', '10.0.0.0/8'));
        self::assertTrue(IpUtils::inCidr('192.168.1.130', '192.168.1.128/25'));
        self::assertFalse(IpUtils::inCidr('192.168.1.127', '192.168.1.128/25'));
        self::assertTrue(IpUtils::inCidr('2001:db8::1', '2001:db8::/32'));
        self::assertFalse(IpUtils::inCidr('10.0.0.1', '2001:db8::/32'));
        self::assertTrue(IpUtils::inCidr('203.0.113.9', '203.0.113.9'));
        self::assertFalse(IpUtils::inCidr('pas-une-ip', '10.0.0.0/8'));
    }

    public function testPrivateAndReservedRanges(): void
    {
        foreach (['127.0.0.1', '10.0.0.5', '172.16.3.4', '192.168.0.1', '169.254.169.254', '100.64.0.1', '0.0.0.0', '::1', 'fd00::1', 'fe80::1', '::ffff:127.0.0.1', 'invalide'] as $ip) {
            self::assertTrue(IpUtils::isPrivateOrReserved($ip), $ip);
        }
        foreach (['1.1.1.1', '8.8.8.8', '2606:4700:4700::1111'] as $ip) {
            self::assertFalse(IpUtils::isPrivateOrReserved($ip), $ip);
        }
    }
}
