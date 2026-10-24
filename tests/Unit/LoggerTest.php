<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Log\Logger;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
    public function testSensitiveKeysAreRedacted(): void
    {
        $clean = Logger::sanitize([
            'password' => 'x', 'db_pass_enc' => 'y', 'api_key' => 'z', 'token_hash' => 'h', 'smtp_password' => 'p',
            'nested' => ['Authorization' => 'Bearer t', 'limit_key' => 'projects'],
            'email' => 'ada@example.com',
        ]);
        foreach (['password', 'db_pass_enc', 'api_key', 'token_hash', 'smtp_password'] as $key) {
            self::assertSame('[masqué]', $clean[$key], $key);
        }
        self::assertIsArray($clean['nested']);
        self::assertSame('[masqué]', $clean['nested']['Authorization']);
        self::assertSame('projects', $clean['nested']['limit_key']);
        self::assertSame('ada@example.com', $clean['email']);
    }

    public function testWritesJsonLinesWithRequestId(): void
    {
        $dir = sys_get_temp_dir() . '/nb-log-' . bin2hex(random_bytes(4));
        $logger = new Logger($dir, 'info', 'test');
        $logger->setRequestId('abc123');
        $logger->debug('ignoré');
        $logger->error('échec', ['exception' => new \RuntimeException('boom')]);
        $files = glob($dir . '/test-*.log.php') ?: [];
        self::assertCount(1, $files);
        $lines = file($files[0], FILE_IGNORE_NEW_LINES) ?: [];
        self::assertCount(2, $lines);
        self::assertSame(rtrim(Logger::GUARD), $lines[0], 'ligne de garde PHP');
        $record = json_decode($lines[1], true);
        self::assertIsArray($record);
        self::assertSame('error', $record['level']);
        self::assertSame('abc123', $record['request_id']);
        self::assertSame('boom', $record['context']['exception']['message']);
        array_map('unlink', $files);
        rmdir($dir);
    }
}
