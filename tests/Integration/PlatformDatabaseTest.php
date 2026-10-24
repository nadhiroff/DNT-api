<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database\Database;
use App\Core\Database\Migrator;
use App\Core\Session\DatabaseSessionHandler;
use App\Repositories\AuditLogRepository;

final class PlatformDatabaseTest extends DatabaseTestCase
{
    public function testMigrationsAreIdempotent(): void
    {
        $migrator = new Migrator(self::db(), dirname(__DIR__, 2) . '/database/migrations');
        self::assertSame([], $migrator->migrate());
        foreach ($migrator->status() as $status) {
            self::assertSame('applied', $status['state'], $status['migration']);
        }
    }

    public function testModifiedMigrationIsRefused(): void
    {
        $dir = sys_get_temp_dir() . '/nb-mig-' . bin2hex(random_bytes(4));
        mkdir($dir);
        foreach (glob(dirname(__DIR__, 2) . '/database/migrations/*.sql') ?: [] as $file) {
            copy($file, $dir . '/' . basename($file));
        }
        $first = (glob($dir . '/*.sql') ?: [])[0];
        file_put_contents($first, "\n-- modification", FILE_APPEND);
        try {
            $migrator = new Migrator(self::db(), $dir);
            self::assertContains('modified', array_column($migrator->status(), 'state'));
            $this->expectException(\RuntimeException::class);
            $migrator->migrate();
        } finally {
            array_map('unlink', glob($dir . '/*.sql') ?: []);
            rmdir($dir);
        }
    }

    public function testDefaultPlanIsSeeded(): void
    {
        $plan = self::db()->selectOne("SELECT slug, JSON_EXTRACT(limits, '$.projects') AS projects, JSON_EXTRACT(limits, '$.db_storage_bytes') AS db FROM plans WHERE is_default = 1");
        self::assertNotNull($plan);
        self::assertSame('free', $plan['slug']);
        self::assertSame('2', (string) $plan['projects']);
        self::assertSame('524288000', (string) $plan['db']);
    }

    public function testAuditLogIsImmutable(): void
    {
        $id = (new AuditLogRepository(self::db()))->insert(null, 'system', 'test.event', 'x', '127.0.0.1', ['k' => 'v']);
        try {
            self::db()->execute('UPDATE audit_logs SET action = ? WHERE id = ?', ['tampered', $id]);
            self::fail('UPDATE aurait dû être refusé');
        } catch (\PDOException $e) {
            self::assertStringContainsString('immuable', $e->getMessage());
        }
        $this->expectException(\PDOException::class);
        self::db()->execute('DELETE FROM audit_logs WHERE id = ?', [$id]);
    }

    public function testTransactionsRollBackIncludingSavepoints(): void
    {
        $db = self::db();
        $db->transaction(function (Database $db): void {
            $db->execute('INSERT INTO rate_limits (bucket_key, window_start, hits) VALUES (?, ?, ?)', [str_repeat('a', 64), 1, 1]);
            try {
                $db->transaction(function (Database $db): void {
                    $db->execute('INSERT INTO rate_limits (bucket_key, window_start, hits) VALUES (?, ?, ?)', [str_repeat('b', 64), 1, 1]);
                    throw new \RuntimeException('annulation interne');
                });
            } catch (\RuntimeException) {
                // Seul le savepoint est annulé.
            }
        });
        self::assertSame(1, (int) $db->scalar('SELECT COUNT(*) FROM rate_limits WHERE bucket_key = ?', [str_repeat('a', 64)]));
        self::assertSame(0, (int) $db->scalar('SELECT COUNT(*) FROM rate_limits WHERE bucket_key = ?', [str_repeat('b', 64)]));

        try {
            $db->transaction(function (Database $db): void {
                $db->execute('INSERT INTO rate_limits (bucket_key, window_start, hits) VALUES (?, ?, ?)', [str_repeat('c', 64), 1, 1]);
                throw new \RuntimeException('annulation');
            });
        } catch (\RuntimeException) {
        }
        self::assertSame(0, (int) $db->scalar('SELECT COUNT(*) FROM rate_limits WHERE bucket_key = ?', [str_repeat('c', 64)]));
        self::assertFalse($db->pdo()->inTransaction());
    }

    public function testNamedLocks(): void
    {
        self::assertSame(42, self::db()->withLock('nb_test_lock', static fn (): int => 42));
    }

    public function testInjectionAttemptsAreTreatedAsData(): void
    {
        $payload = "x' OR '1'='1'; DROP TABLE users; --";
        self::assertNull(self::db()->selectOne('SELECT id FROM users WHERE email = ?', [$payload]));
        self::assertSame(1, (int) self::db()->scalar("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'"));
    }

    public function testSessionHandlerStoresHashedIds(): void
    {
        $handler = new DatabaseSessionHandler(self::db(), 7200);
        $handler->setContext(null, '203.0.113.9', 'PHPUnit');
        $sid = bin2hex(random_bytes(20));
        self::assertFalse($handler->validateId($sid));
        self::assertTrue($handler->write($sid, 'payload|s:3:"abc";'));
        self::assertTrue($handler->validateId($sid));
        self::assertSame('payload|s:3:"abc";', $handler->read($sid));
        self::assertNull(self::db()->scalar('SELECT 1 FROM sessions WHERE id = ?', [$sid]), 'identifiant jamais stocké en clair');
        self::assertSame('203.0.113.9', self::db()->scalar('SELECT ip FROM sessions WHERE id = ?', [DatabaseSessionHandler::hashId($sid)]));

        self::db()->execute('UPDATE sessions SET last_activity = ? WHERE id = ?', [time() - 8000, DatabaseSessionHandler::hashId($sid)]);
        self::assertSame('', $handler->read($sid), 'session expirée illisible');
        self::assertGreaterThanOrEqual(1, $handler->gc(7200));
        self::assertTrue($handler->destroy($sid));
    }
}
