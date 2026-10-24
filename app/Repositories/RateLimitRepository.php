<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * Compteurs par fenêtre fixe (repli MySQL du rate limiting).
 */
final class RateLimitRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Incrémente atomiquement le compteur de la fenêtre et retourne le total.
     */
    public function hit(string $key, int $windowStart): int
    {
        $this->db->execute(
            'INSERT INTO rate_limits (bucket_key, window_start, hits) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE hits = hits + 1',
            [$key, $windowStart],
        );

        return (int) $this->db->scalar('SELECT hits FROM rate_limits WHERE bucket_key = ? AND window_start = ?', [$key, $windowStart]);
    }

    public function purgeBefore(int $timestamp): int
    {
        return $this->db->execute('DELETE FROM rate_limits WHERE window_start < ?', [$timestamp]);
    }
}
