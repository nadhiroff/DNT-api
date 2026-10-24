<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Application;
use App\Core\Database\ConnectionFactory;
use App\Core\Database\Database;
use App\Core\Database\Identifier;
use App\Core\Database\Migrator;
use PHPUnit\Framework\TestCase;

/**
 * Base des tests d'intégration : crée une base MySQL jetable, applique les migrations,
 * puis la supprime. Ignorés si NB_TEST_DB_USER n'est pas défini.
 *
 * Variables : NB_TEST_DB_HOST, NB_TEST_DB_PORT, NB_TEST_DB_USER, NB_TEST_DB_PASSWORD
 * (compte autorisé à créer/supprimer des bases novabase_test_* et des triggers).
 */
abstract class DatabaseTestCase extends TestCase
{
    protected static ?string $database = null;

    protected static ?Database $db = null;

    public static function setUpBeforeClass(): void
    {
        $user = getenv('NB_TEST_DB_USER');
        if ($user === false || $user === '') {
            return;
        }
        static::$database = 'novabase_test_' . bin2hex(random_bytes(4));
        $server = self::server();
        $server->execute('CREATE DATABASE ' . Identifier::quote((string) static::$database) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
        static::$db = new Database(ConnectionFactory::make(self::credentials() + ['database' => static::$database]));
        (new Migrator(static::$db, dirname(__DIR__, 2) . '/database/migrations'))->migrate();
    }

    public static function tearDownAfterClass(): void
    {
        if (static::$database !== null) {
            self::server()->execute('DROP DATABASE IF EXISTS ' . Identifier::quote(static::$database));
            static::$database = null;
            static::$db = null;
        }
    }

    protected function setUp(): void
    {
        if (static::$db === null) {
            self::markTestSkipped('Base de test non configurée (NB_TEST_DB_USER).');
        }
    }

    protected static function db(): Database
    {
        if (static::$db === null) {
            throw new \LogicException('Base de test indisponible.');
        }

        return static::$db;
    }

    /**
     * Application configurée sur la base de test (compte applicatif = compte admin de test).
     */
    protected static function app(): Application
    {
        $c = self::credentials();
        foreach ([
            'APP_ENV' => 'testing',
            'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)),
            'APP_URL' => 'http://nb.test',
            'APP_URL_REWRITE' => 'true',
            'LOG_LEVEL' => 'critical',
            'DB_HOST' => (string) $c['host'],
            'DB_PORT' => (string) $c['port'],
            'DB_DATABASE' => (string) static::$database,
            'DB_USERNAME' => (string) $c['username'],
            'DB_PASSWORD' => (string) $c['password'],
            'DB_ADMIN_USERNAME' => (string) $c['username'],
            'DB_ADMIN_PASSWORD' => (string) $c['password'],
            'ARGON_MEMORY_COST' => '8192',
            'ARGON_TIME_COST' => '1',
        ] as $key => $value) {
            $_ENV[$key] = $value;
        }

        return Application::create(dirname(__DIR__, 2), false);
    }

    /** @return array{host: string, port: int, username: string, password: string} */
    protected static function credentials(): array
    {
        return [
            'host' => getenv('NB_TEST_DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('NB_TEST_DB_PORT') ?: 3306),
            'username' => (string) getenv('NB_TEST_DB_USER'),
            'password' => (string) getenv('NB_TEST_DB_PASSWORD'),
        ];
    }

    private static function server(): Database
    {
        return new Database(ConnectionFactory::make(self::credentials()));
    }
}
