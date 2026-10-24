<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

final class LoginThrottleRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Secondes restantes de blocage (0 si aucun).
     */
    public function lockedFor(string $key): int
    {
        $remaining = $this->db->scalar(
            'SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), locked_until)) FROM login_throttles WHERE throttle_key = ? AND locked_until IS NOT NULL',
            [$key],
        );

        return $remaining === null ? 0 : (int) $remaining;
    }

    /**
     * Incrémente le compteur d'échecs (remis à zéro après $resetAfter secondes sans échec)
     * et retourne le nouveau total.
     */
    public function hit(string $key, int $resetAfter): int
    {
        $this->db->execute(
            'INSERT INTO login_throttles (throttle_key, failures, last_failure_at) VALUES (?, 1, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                 failures = IF(last_failure_at < UTC_TIMESTAMP() - INTERVAL ? SECOND, 1, failures + 1),
                 last_failure_at = UTC_TIMESTAMP()',
            [$key, $resetAfter],
        );

        return (int) $this->db->scalar('SELECT failures FROM login_throttles WHERE throttle_key = ?', [$key]);
    }

    public function lock(string $key, int $seconds): void
    {
        $this->db->execute('UPDATE login_throttles SET locked_until = UTC_TIMESTAMP() + INTERVAL ? SECOND WHERE throttle_key = ?', [$seconds, $key]);
    }

    public function clear(string $key): void
    {
        $this->db->execute('DELETE FROM login_throttles WHERE throttle_key = ?', [$key]);
    }

    public function purge(int $olderThanSeconds): int
    {
        return $this->db->execute(
            'DELETE FROM login_throttles WHERE last_failure_at < UTC_TIMESTAMP() - INTERVAL ? SECOND AND (locked_until IS NULL OR locked_until < UTC_TIMESTAMP())',
            [$olderThanSeconds],
        );
    }
}
