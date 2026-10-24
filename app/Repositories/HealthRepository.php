<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * Requêtes de supervision (endpoint /health).
 */
final class HealthRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function ping(): bool
    {
        return (int) $this->db->scalar('SELECT 1') === 1;
    }

    /**
     * Nombre de tâches disponibles depuis plus de $seconds secondes sans avoir été prises.
     */
    public function stalledJobs(int $seconds): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM jobs WHERE reserved_at IS NULL AND available_at < (UTC_TIMESTAMP() - INTERVAL ? SECOND)',
            [$seconds],
        );
    }
}
