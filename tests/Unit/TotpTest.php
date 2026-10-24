<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Security\Totp;
use PHPUnit\Framework\TestCase;

final class TotpTest extends TestCase
{
    /** Secret ASCII « 12345678901234567890 » de la RFC 6238, en base32. */
    private const string RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function testRfc6238Vectors(): void
    {
        $totp = new Totp(8);
        self::assertSame('94287082', $totp->code(self::RFC_SECRET, 59));
        self::assertSame('07081804', $totp->code(self::RFC_SECRET, 1111111109));
        self::assertSame('14050471', $totp->code(self::RFC_SECRET, 1111111111));
        self::assertSame('89005924', $totp->code(self::RFC_SECRET, 1234567890));
        self::assertSame('69279037', $totp->code(self::RFC_SECRET, 2000000000));
    }

    public function testBase32RoundTrip(): void
    {
        self::assertSame(self::RFC_SECRET, Totp::base32Encode('12345678901234567890'));
        self::assertSame('12345678901234567890', Totp::base32Decode(self::RFC_SECRET));
        self::assertSame(32, strlen(Totp::generateSecret()));
    }

    public function testVerifyAcceptsAdjacentStepAndReturnsIt(): void
    {
        $totp = new Totp();
        $secret = Totp::generateSecret();
        $now = 1_800_000_000;
        $previous = $totp->code($secret, $now - 30);
        self::assertSame(intdiv($now, 30) - 1, $totp->verify($secret, $previous, null, 1, $now));
        self::assertNull($totp->verify($secret, $totp->code($secret, $now - 90), null, 1, $now));
    }

    public function testReplayIsRejected(): void
    {
        $totp = new Totp();
        $secret = Totp::generateSecret();
        $now = 1_800_000_000;
        $code = $totp->code($secret, $now);
        $step = $totp->verify($secret, $code, null, 1, $now);
        self::assertNotNull($step);
        self::assertNull($totp->verify($secret, $code, $step, 1, $now));
    }

    public function testMalformedCodes(): void
    {
        $totp = new Totp();
        $secret = Totp::generateSecret();
        self::assertNull($totp->verify($secret, 'abcdef'));
        self::assertNull($totp->verify($secret, '12345'));
        $code = $totp->code($secret);
        self::assertNotNull($totp->verify($secret, substr($code, 0, 3) . ' ' . substr($code, 3)));
    }

    public function testProvisioningUri(): void
    {
        $uri = (new Totp())->provisioningUri('ABC', 'ada@example.com', 'NovaBase');
        self::assertStringStartsWith('otpauth://totp/NovaBase:ada%40example.com?secret=ABC&issuer=NovaBase', $uri);
    }
}
