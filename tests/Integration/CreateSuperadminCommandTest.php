<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Console\Commands\CreateSuperadminCommand;
use App\Core\Console\Input;
use App\Core\Console\Kernel;
use App\Core\Console\Output;
use App\Core\Security\PasswordHasher;
use App\Core\Security\Totp;
use App\Repositories\UserRepository;
use App\Services\Crypto;

final class CreateSuperadminCommandTest extends DatabaseTestCase
{
    private const string SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    /**
     * @return array{0: int, 1: string, 2: \App\Core\Application}
     */
    private function runCommand(string $email, string $stdin): array
    {
        $app = self::app();
        // Secret fixe pour pouvoir calculer le code TOTP attendu.
        $app->container()->instance(Totp::class, new class () extends Totp {
            public function newSecret(): string
            {
                return 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
            }
        });
        $in = fopen('php://memory', 'r+');
        $out = fopen('php://memory', 'r+');
        self::assertIsResource($in);
        self::assertIsResource($out);
        fwrite($in, $stdin);
        rewind($in);
        $kernel = new Kernel($app, [CreateSuperadminCommand::class]);
        $code = $kernel->handle(
            ['bin/console', 'create-superadmin'],
            new Output($out, false),
            new Input(['--email=' . $email, '--name=Ada Lovelace', '--password-stdin'], $in, false),
        );
        rewind($out);

        return [$code, (string) stream_get_contents($out), $app];
    }

    public function testCreatesSuperadminWithVerifiedTotpAndRecoveryCodes(): void
    {
        $code = (new Totp())->code(self::SECRET);
        [$exit, $output, $app] = $this->runCommand('Root@Example.com', "Vélo-Lune-Orage-42\n" . $code . "\n");

        self::assertSame(0, $exit, $output);
        self::assertStringContainsString('Superadmin créé', $output);
        self::assertStringNotContainsString('Vélo-Lune-Orage-42', $output);
        preg_match_all('/[2-9A-HJ-NP-Z]{5}-[2-9A-HJ-NP-Z]{5}/', $output, $codes);
        self::assertCount(10, $codes[0]);

        $user = $app->container()->get(UserRepository::class)->findByEmail('root@example.com');
        self::assertNotNull($user);
        self::assertSame('superadmin', $user['role']);
        self::assertNull($user['plan_id']);
        self::assertNotNull($user['email_verified_at']);
        self::assertNotNull($user['totp_enabled_at']);
        self::assertNotNull($user['totp_last_step']);
        self::assertNotNull($user['totp_secret_enc']);
        self::assertStringNotContainsString(self::SECRET, $user['totp_secret_enc']);
        self::assertSame(self::SECRET, $app->container()->get(Crypto::class)->decrypt($user['totp_secret_enc'], 'totp_secret'));
        self::assertStringStartsWith('$argon2id$', $user['password_hash']);
        self::assertTrue($app->container()->get(PasswordHasher::class)->verify('Vélo-Lune-Orage-42', $user['password_hash']));

        self::assertSame(10, (int) self::db()->scalar('SELECT COUNT(*) FROM user_recovery_codes WHERE user_id = ?', [$user['id']]));
        $audit = self::db()->selectOne("SELECT actor_type, target, metadata FROM audit_logs WHERE action = 'superadmin.created'");
        self::assertNotNull($audit);
        self::assertSame('cli', $audit['actor_type']);
        self::assertSame('user:' . $user['id'], $audit['target']);
    }

    public function testDuplicateEmailIsRefused(): void
    {
        [$exit, $output] = $this->runCommand('root@example.com', "Vélo-Lune-Orage-42\n000000\n");
        self::assertSame(1, $exit);
        self::assertStringContainsString('existe déjà', $output);
    }

    public function testWrongTotpCreatesNothing(): void
    {
        [$exit, $output] = $this->runCommand('other@example.com', "Vélo-Lune-Orage-42\n000000\n000001\n000002\n");
        self::assertSame(1, $exit);
        self::assertStringContainsString('aucun compte', $output);
        self::assertNull(self::db()->scalar('SELECT 1 FROM users WHERE email = ?', ['other@example.com']));
    }

    public function testWeakPasswordIsRefused(): void
    {
        [$exit, $output] = $this->runCommand('weak@example.com', "azertyuiop\n");
        self::assertSame(1, $exit);
        self::assertStringContainsString('plus utilisés', $output);
    }
}
