<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Crypto;
use PHPUnit\Framework\TestCase;

final class CryptoTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $crypto = new Crypto(Crypto::generateKey());
        $cipher = $crypto->encrypt('mot de passe SMTP', 'smtp_password');
        self::assertStringStartsWith('v1:', $cipher);
        self::assertStringNotContainsString('SMTP', $cipher);
        self::assertSame('mot de passe SMTP', $crypto->decrypt($cipher, 'smtp_password'));
    }

    public function testNonceIsRandom(): void
    {
        $crypto = new Crypto(Crypto::generateKey());
        self::assertNotSame($crypto->encrypt('x'), $crypto->encrypt('x'));
    }

    public function testContextIsBound(): void
    {
        $crypto = new Crypto(Crypto::generateKey());
        $cipher = $crypto->encrypt('secret', 'totp_secret');
        $this->expectException(\RuntimeException::class);
        $crypto->decrypt($cipher, 'smtp_password');
    }

    public function testWrongKeyFails(): void
    {
        $cipher = (new Crypto(Crypto::generateKey()))->encrypt('secret');
        $this->expectException(\RuntimeException::class);
        (new Crypto(Crypto::generateKey()))->decrypt($cipher);
    }

    public function testTamperingIsDetected(): void
    {
        $crypto = new Crypto(Crypto::generateKey());
        $raw = base64_decode(substr($crypto->encrypt('secret'), 3), true);
        self::assertIsString($raw);
        $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 1);
        $this->expectException(\RuntimeException::class);
        $crypto->decrypt('v1:' . base64_encode($raw));
    }

    public function testInvalidMasterKeyIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        new Crypto('base64:' . base64_encode('trop-courte'));
    }

    public function testHmacDependsOnContext(): void
    {
        $crypto = new Crypto(Crypto::generateKey());
        self::assertSame($crypto->hmac('a', 'x'), $crypto->hmac('a', 'x'));
        self::assertNotSame($crypto->hmac('a', 'x'), $crypto->hmac('a', 'y'));
    }
}
