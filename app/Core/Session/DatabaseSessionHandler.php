<?php

declare(strict_types=1);

namespace App\Core\Session;

use App\Core\Database\Database;
use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;

/**
 * Stockage des sessions en MySQL (table sessions) : sans état côté serveur web, donc compatible
 * multi-serveurs. Les identifiants de session ne sont jamais stockés en clair (SHA-256).
 */
final class DatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private ?int $userId = null;

    private string $ip = '';

    private string $userAgent = '';

    public function __construct(
        private readonly Database $db,
        private readonly int $lifetime,
    ) {
    }

    public function setContext(?int $userId, string $ip, string $userAgent): void
    {
        $this->userId = $userId;
        $this->ip = mb_substr($ip, 0, 45);
        $this->userAgent = mb_substr($userAgent, 0, 255);
    }

    public function setUserId(?int $userId): void
    {
        $this->userId = $userId;
    }

    public static function hashId(string $id): string
    {
        return hash('sha256', $id);
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        $payload = $this->db->scalar(
            'SELECT payload FROM sessions WHERE id = ? AND last_activity >= ?',
            [self::hashId($id), time() - $this->lifetime],
        );

        return is_string($payload) ? $payload : '';
    }

    public function write(string $id, string $data): bool
    {
        $this->db->execute(
            'INSERT INTO sessions (id, user_id, ip, user_agent, payload, last_activity, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), ip = VALUES(ip), user_agent = VALUES(user_agent),
                                     payload = VALUES(payload), last_activity = VALUES(last_activity)',
            [self::hashId($id), $this->userId, $this->ip, $this->userAgent, $data, time(), time()],
        );

        return true;
    }

    public function destroy(string $id): bool
    {
        $this->db->execute('DELETE FROM sessions WHERE id = ?', [self::hashId($id)]);

        return true;
    }

    public function gc(int $max_lifetime): int
    {
        return $this->db->execute('DELETE FROM sessions WHERE last_activity < ?', [time() - $max_lifetime]);
    }

    public function validateId(string $id): bool
    {
        return $this->db->scalar(
            'SELECT 1 FROM sessions WHERE id = ? AND last_activity >= ?',
            [self::hashId($id), time() - $this->lifetime],
        ) !== null;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $this->db->execute(
            'UPDATE sessions SET last_activity = ?, user_id = ?, ip = ? WHERE id = ?',
            [time(), $this->userId, $this->ip, self::hashId($id)],
        );

        return true;
    }
}
