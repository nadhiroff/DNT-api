<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * Surcharges individuelles de limites (ligne absente = limite du plan ; valeur NULL = illimité).
 */
final class UserLimitRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array{found: bool, value: int|null}
     */
    public function get(int $userId, string $key): array
    {
        $row = $this->db->selectOne('SELECT value FROM user_limits WHERE user_id = ? AND limit_key = ?', [$userId, $key]);

        return $row === null ? ['found' => false, 'value' => null] : ['found' => true, 'value' => $row['value'] !== null ? (int) $row['value'] : null];
    }
}
