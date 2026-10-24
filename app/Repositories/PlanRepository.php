<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

final class PlanRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function defaultPlanId(): ?int
    {
        $id = $this->db->scalar('SELECT id FROM plans WHERE is_default = 1 ORDER BY sort, id LIMIT 1');

        return $id !== null ? (int) $id : null;
    }
}
