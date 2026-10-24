<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Security\PasswordHasher;
use App\Core\Security\PasswordPolicy;
use App\Services\Crypto;
use App\Services\RecoveryCodeService;
use PHPUnit\Framework\TestCase;

final class PasswordTest extends TestCase
{
    private static function policy(): PasswordPolicy
    {
        return new PasswordPolicy(dirname(__DIR__, 2) . '/resources/security/common-passwords.txt');
    }

    public function testMinimumLength(): void
    {
        self::assertNotSame([], self::policy()->validate('Court1!'));
    }

    public function testCommonPasswordsAreRejected(): void
    {
        foreach (['azertyuiop', '1234567890', 'Motdepasse', 'qwertyuiop', 'password123'] as $password) {
            self::assertTrue(self::policy()->isCommon($password), $password);
        }
        self::assertFalse(self::policy()->isCommon('Vélo-Lune-Orage-42'));
    }

    public function testRepetitiveAndPersonalPasswords(): void
    {
        self::assertNotSame([], self::policy()->validate('aaaaaaaaaaaa'));
        self::assertNotSame([], self::policy()->validate('xx-adalovelace-2026', 'adalovelace@example.com'));
        self::assertSame([], self::policy()->validate('Vélo-Lune-Orage-42', 'ada@example.com', 'Ada'));
    }

    public function testArgon2idHashing(): void
    {
        $hasher = new PasswordHasher(8192, 1, 1);
        $hash = $hasher->hash('Vélo-Lune-Orage-42');
        self::assertStringStartsWith('$argon2id$', $hash);
        self::assertTrue($hasher->verify('Vélo-Lune-Orage-42', $hash));
        self::assertFalse($hasher->verify('autre', $hash));
        self::assertFalse($hasher->needsRehash($hash));
        self::assertTrue((new PasswordHasher(16384, 2, 1))->needsRehash($hash));
    }

    public function testRecoveryCodes(): void
    {
        $service = new RecoveryCodeService(new Crypto(Crypto::generateKey()));
        $codes = $service->generate();
        self::assertCount(10, array_unique($codes));
        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/^[2-9A-HJ-NP-Z]{5}-[2-9A-HJ-NP-Z]{5}$/', $code);
        }
        self::assertSame($service->hash($codes[0]), $service->hash(strtolower(str_replace('-', ' ', $codes[0]))));
    }
}
