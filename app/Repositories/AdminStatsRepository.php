<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * Indicateurs globaux du panneau superadmin.
 */
final class AdminStatsRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Inscriptions par jour (comptes utilisateurs).
     *
     * @return array<string, int>
     */
    public function signupsDaily(int $days = 30): array
    {
        return ApiUsageRepository::fillDays($this->db->select(
            "SELECT DATE(created_at) AS day, COUNT(*) AS total FROM users WHERE role = 'user' AND created_at >= UTC_DATE() - INTERVAL ? DAY GROUP BY DATE(created_at)",
            [$days - 1],
        ), $days);
    }

    /**
     * @return array{users: int, verified: int, new_week: int, superadmins: int, testimonials: int, published: int, pending_jobs: int, failed_jobs: int}
     */
    public function overview(): array
    {
        $row = $this->db->selectOne(
            "SELECT
                (SELECT COUNT(*) FROM users WHERE role = 'user') AS users,
                (SELECT COUNT(*) FROM users WHERE role = 'user' AND email_verified_at IS NOT NULL) AS verified,
                (SELECT COUNT(*) FROM users WHERE role = 'user' AND created_at >= UTC_TIMESTAMP() - INTERVAL 7 DAY) AS new_week,
                (SELECT COUNT(*) FROM users WHERE role = 'superadmin') AS superadmins,
                (SELECT COUNT(*) FROM testimonials) AS testimonials,
                (SELECT COUNT(*) FROM testimonials WHERE is_published = 1) AS published,
                (SELECT COUNT(*) FROM jobs) AS pending_jobs,
                (SELECT COUNT(*) FROM failed_jobs) AS failed_jobs",
        ) ?? [];

        return [
            'users' => (int) ($row['users'] ?? 0),
            'verified' => (int) ($row['verified'] ?? 0),
            'new_week' => (int) ($row['new_week'] ?? 0),
            'superadmins' => (int) ($row['superadmins'] ?? 0),
            'testimonials' => (int) ($row['testimonials'] ?? 0),
            'published' => (int) ($row['published'] ?? 0),
            'pending_jobs' => (int) ($row['pending_jobs'] ?? 0),
            'failed_jobs' => (int) ($row['failed_jobs'] ?? 0),
        ];
    }
}
