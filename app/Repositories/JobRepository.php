<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * File d'attente MySQL : réservation concurrente par SELECT … FOR UPDATE SKIP LOCKED.
 *
 * @phpstan-type JobRow array{id: int, queue: string, type: string, payload: string, attempts: int, max_attempts: int}
 */
final class JobRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function push(string $queue, string $type, string $payload, int $maxAttempts, int $delaySeconds = 0): int
    {
        return $this->db->insert(
            'INSERT INTO jobs (queue, type, payload, max_attempts, available_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP() + INTERVAL ? SECOND)',
            [$queue, $type, $payload, $maxAttempts, $delaySeconds],
        );
    }

    /**
     * Réserve la prochaine tâche disponible (plusieurs workers peuvent tourner en parallèle).
     * Les tâches réservées depuis plus de $staleSeconds (worker tombé) redeviennent disponibles.
     *
     * @return JobRow|null
     */
    public function reserve(string $queue, string $workerId, int $staleSeconds): ?array
    {
        return $this->db->transaction(function (Database $db) use ($queue, $workerId, $staleSeconds): ?array {
            $row = $db->selectOne(
                'SELECT id, queue, type, payload, attempts, max_attempts FROM jobs
                 WHERE queue = ? AND available_at <= UTC_TIMESTAMP()
                   AND (reserved_at IS NULL OR reserved_at < UTC_TIMESTAMP() - INTERVAL ? SECOND)
                 ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED',
                [$queue, $staleSeconds],
            );
            if ($row === null) {
                return null;
            }
            $db->execute('UPDATE jobs SET reserved_at = UTC_TIMESTAMP(), reserved_by = ?, attempts = attempts + 1 WHERE id = ?', [$workerId, (int) $row['id']]);

            return [
                'id' => (int) $row['id'],
                'queue' => (string) $row['queue'],
                'type' => (string) $row['type'],
                'payload' => (string) $row['payload'],
                'attempts' => (int) $row['attempts'] + 1,
                'max_attempts' => (int) $row['max_attempts'],
            ];
        });
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM jobs WHERE id = ?', [$id]);
    }

    public function release(int $id, int $delaySeconds, string $error): void
    {
        $this->db->execute(
            'UPDATE jobs SET reserved_at = NULL, reserved_by = NULL, available_at = UTC_TIMESTAMP() + INTERVAL ? SECOND, last_error = ? WHERE id = ?',
            [$delaySeconds, mb_substr($error, 0, 2000), $id],
        );
    }

    /**
     * @param JobRow $job
     */
    public function fail(array $job, string $error): void
    {
        $this->db->transaction(function (Database $db) use ($job, $error): void {
            $db->execute(
                'INSERT INTO failed_jobs (job_id, queue, type, payload, attempts, exception) VALUES (?, ?, ?, ?, ?, ?)',
                [$job['id'], $job['queue'], $job['type'], $job['payload'], $job['attempts'], mb_substr($error, 0, 10000)],
            );
            $db->execute('DELETE FROM jobs WHERE id = ?', [$job['id']]);
        });
    }
}
