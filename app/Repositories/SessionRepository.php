<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * Sessions actives d'un utilisateur (affichage et révocation).
 */
final class SessionRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return list<array{id: string, ip: string, user_agent: string, last_activity: int, created_at: int}>
     */
    public function activeForUser(int $userId, int $lifetime): array
    {
        $rows = $this->db->select(
            'SELECT id, ip, user_agent, last_activity, created_at FROM sessions WHERE user_id = ? AND last_activity >= ? ORDER BY last_activity DESC LIMIT 50',
            [$userId, time() - $lifetime],
        );

        return array_map(static fn (array $r): array => [
            'id' => (string) $r['id'],
            'ip' => (string) $r['ip'],
            'user_agent' => (string) $r['user_agent'],
            'last_activity' => (int) $r['last_activity'],
            'created_at' => (int) $r['created_at'],
        ], $rows);
    }

    public function revoke(int $userId, string $hashedId): bool
    {
        return $this->db->execute('DELETE FROM sessions WHERE id = ? AND user_id = ?', [$hashedId, $userId]) === 1;
    }

    /**
     * Révoque toutes les sessions de l'utilisateur sauf $keepHashedId (null = toutes).
     */
    public function revokeAllExcept(int $userId, ?string $keepHashedId): int
    {
        return $keepHashedId === null
            ? $this->db->execute('DELETE FROM sessions WHERE user_id = ?', [$userId])
            : $this->db->execute('DELETE FROM sessions WHERE user_id = ? AND id <> ?', [$userId, $keepHashedId]);
    }
}
