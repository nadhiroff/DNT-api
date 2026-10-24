<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * Consommation des quotas : taille des bases (instantanés), requêtes du mois, e-mails du jour.
 */
final class UsageRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function saveSnapshot(int $projectId, int $userId, int $dbBytes): void
    {
        $this->db->execute(
            'INSERT INTO usage_snapshots (project_id, user_id, db_bytes, computed_at) VALUES (?, ?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), db_bytes = VALUES(db_bytes), computed_at = VALUES(computed_at)',
            [$projectId, $userId, $dbBytes],
        );
    }

    public function dbBytesForOwner(int $userId): int
    {
        return (int) $this->db->scalar('SELECT COALESCE(SUM(db_bytes), 0) FROM usage_snapshots WHERE user_id = ?', [$userId]);
    }

    public function apiRequestsThisMonth(int $userId): int
    {
        return (int) $this->db->scalar(
            "SELECT COALESCE(SUM(u.requests), 0) FROM api_usage_hourly u JOIN projects p ON p.id = u.project_id
             WHERE p.owner_id = ? AND u.hour >= DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m-01')",
            [$userId],
        );
    }

    public function emailsToday(int $userId): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM email_logs e JOIN projects p ON p.id = e.project_id WHERE p.owner_id = ? AND e.created_at >= UTC_DATE()',
            [$userId],
        );
    }

    /**
     * @return list<array{id: int, owner_id: int, db_name: string}>
     */
    public function activeProjects(): array
    {
        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'owner_id' => (int) $r['owner_id'], 'db_name' => (string) $r['db_name']], $this->db->select("SELECT id, owner_id, db_name FROM projects WHERE status IN ('active', 'suspended')"));
    }
}
