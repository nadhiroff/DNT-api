<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * Journal des connexions.
 *
 * @phpstan-type LoginEventRow array{id: int, user_id: int|null, email: string|null, user_name: string|null, event: string, ip: string, user_agent: string, browser: string, os: string, device: string, created_at: string}
 */
final class LoginEventRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function insert(?int $userId, ?string $email, string $event, string $ip, string $userAgent, string $browser, string $os, string $device): void
    {
        $this->db->execute(
            'INSERT INTO login_events (user_id, email, event, ip, user_agent, browser, os, device) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $email !== null ? mb_substr(mb_strtolower($email), 0, 254) : null, $event, mb_substr($ip, 0, 45), mb_substr($userAgent, 0, 255), $browser, $os, $device],
        );
    }

    /**
     * @return list<LoginEventRow>
     */
    public function latest(string $event, string $search, int $afterId, int $limit, int $offset): array
    {
        $rows = $this->db->select(
            "SELECT e.id, e.user_id, e.email, u.name AS user_name, e.event, e.ip, e.user_agent, e.browser, e.os, e.device,
                    DATE_FORMAT(e.created_at, '%Y-%m-%d %H:%i:%s') AS created_at
             FROM login_events e LEFT JOIN users u ON u.id = e.user_id
             WHERE (? = '' OR e.event = ?) AND (? = '' OR e.email LIKE ? OR u.name LIKE ? OR e.ip LIKE ?) AND e.id > ?
             ORDER BY e.id DESC LIMIT ? OFFSET ?",
            [$event, $event, $search, '%' . $search . '%', '%' . $search . '%', $search . '%', $afterId, $limit, $offset],
        );

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'user_id' => $r['user_id'] !== null ? (int) $r['user_id'] : null,
            'email' => $r['email'] !== null ? (string) $r['email'] : null,
            'user_name' => $r['user_name'] !== null ? (string) $r['user_name'] : null,
            'event' => (string) $r['event'],
            'ip' => (string) $r['ip'],
            'user_agent' => (string) $r['user_agent'],
            'browser' => (string) $r['browser'],
            'os' => (string) $r['os'],
            'device' => (string) $r['device'],
            'created_at' => (string) $r['created_at'],
        ], $rows);
    }

    /**
     * @return array{logins: int, failed: int, unique_users: int}
     */
    public function summary(int $hours): array
    {
        $row = $this->db->selectOne(
            "SELECT SUM(event = 'login') AS logins, SUM(event IN ('login_failed', '2fa_failed', 'locked')) AS failed,
                    COUNT(DISTINCT CASE WHEN event = 'login' THEN user_id END) AS unique_users
             FROM login_events WHERE created_at >= UTC_TIMESTAMP() - INTERVAL ? HOUR",
            [$hours],
        ) ?? [];

        return ['logins' => (int) ($row['logins'] ?? 0), 'failed' => (int) ($row['failed'] ?? 0), 'unique_users' => (int) ($row['unique_users'] ?? 0)];
    }
}
