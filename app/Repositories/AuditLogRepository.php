<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * Journal d'audit en ajout seul : aucune méthode de modification ou de suppression.
 */
final class AuditLogRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function insert(?int $actorId, string $actorType, string $action, ?string $target, ?string $ip, array $metadata): int
    {
        return $this->db->insert(
            'INSERT INTO audit_logs (actor_id, actor_type, action, target, ip, metadata) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $actorId,
                $actorType,
                $action,
                $target !== null ? mb_substr($target, 0, 191) : null,
                $ip,
                $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function latest(int $limit = 50, int $offset = 0): array
    {
        return $this->db->select(
            'SELECT id, actor_id, actor_type, action, target, ip, metadata, created_at FROM audit_logs ORDER BY id DESC LIMIT ? OFFSET ?',
            [max(1, min($limit, 200)), max(0, $offset)],
        );
    }
}
