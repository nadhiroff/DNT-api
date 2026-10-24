<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * Compteurs de requêtes API agrégés par heure.
 */
final class ApiUsageRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function hit(int $projectId, bool $error): void
    {
        $this->db->execute(
            "INSERT INTO api_usage_hourly (project_id, hour, requests, errors) VALUES (?, DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m-%d %H:00:00'), 1, ?)
             ON DUPLICATE KEY UPDATE requests = requests + 1, errors = errors + VALUES(errors)",
            [$projectId, $error ? 1 : 0],
        );
    }

    /**
     * Requêtes par jour sur $days jours (projets d'un propriétaire, ou tous si $ownerId est null).
     *
     * @return array<string, int> date (Y-m-d) => requêtes, jours sans trafic inclus
     */
    public function daily(?int $ownerId, int $days = 30): array
    {
        $rows = $this->db->select(
            'SELECT DATE(u.hour) AS day, SUM(u.requests) AS total FROM api_usage_hourly u JOIN projects p ON p.id = u.project_id
             WHERE u.hour >= UTC_DATE() - INTERVAL ? DAY AND (? IS NULL OR p.owner_id = ?)
             GROUP BY DATE(u.hour)',
            [$days - 1, $ownerId, $ownerId],
        );

        return self::fillDays($rows, $days);
    }

    /**
     * @return array<string, int>
     */
    public function dailyForProject(int $projectId, int $days = 30): array
    {
        return self::fillDays($this->db->select(
            'SELECT DATE(hour) AS day, SUM(requests) AS total FROM api_usage_hourly WHERE project_id = ? AND hour >= UTC_DATE() - INTERVAL ? DAY GROUP BY DATE(hour)',
            [$projectId, $days - 1],
        ), $days);
    }

    public function total(?int $ownerId, int $days = 30): int
    {
        return (int) $this->db->scalar(
            'SELECT COALESCE(SUM(u.requests), 0) FROM api_usage_hourly u JOIN projects p ON p.id = u.project_id
             WHERE u.hour >= UTC_DATE() - INTERVAL ? DAY AND (? IS NULL OR p.owner_id = ?)',
            [$days - 1, $ownerId, $ownerId],
        );
    }

    public function totalForProject(int $projectId, int $days = 30): int
    {
        return (int) $this->db->scalar(
            'SELECT COALESCE(SUM(requests), 0) FROM api_usage_hourly WHERE project_id = ? AND hour >= UTC_DATE() - INTERVAL ? DAY',
            [$projectId, $days - 1],
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, int>
     */
    public static function fillDays(array $rows, int $days): array
    {
        $series = [];
        $today = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
        for ($i = $days - 1; $i >= 0; --$i) {
            $series[$today->modify('-' . $i . ' days')->format('Y-m-d')] = 0;
        }
        foreach ($rows as $row) {
            $day = (string) $row['day'];
            if (array_key_exists($day, $series)) {
                $series[$day] = (int) $row['total'];
            }
        }

        return $series;
    }
}
