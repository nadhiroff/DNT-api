<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\HealthRepository;
use Throwable;

/**
 * Vérifie la base, la file d'attente et l'espace disque.
 */
final class HealthService
{
    private const float MIN_FREE_RATIO = 0.05;

    public function __construct(
        private readonly HealthRepository $repository,
        private readonly string $storagePath,
    ) {
    }

    /**
     * @return array{status: string, checks: array<string, array{status: string, detail?: string}>}
     */
    public function check(): array
    {
        $checks = [];

        try {
            $checks['database'] = ['status' => $this->repository->ping() ? 'ok' : 'fail'];
        } catch (Throwable) {
            $checks['database'] = ['status' => 'fail'];
        }

        if ($checks['database']['status'] === 'ok') {
            try {
                $stalled = $this->repository->stalledJobs(300);
                $checks['queue'] = $stalled === 0
                    ? ['status' => 'ok']
                    : ['status' => 'degraded', 'detail' => $stalled . ' tâche(s) en attente depuis plus de 5 min'];
            } catch (Throwable) {
                $checks['queue'] = ['status' => 'fail'];
            }
        }

        $free = @disk_free_space($this->storagePath);
        $total = @disk_total_space($this->storagePath);
        if ($free === false || $total === false || $total <= 0) {
            $checks['disk'] = ['status' => 'unknown'];
        } else {
            $ratio = $free / $total;
            $checks['disk'] = ['status' => $ratio >= self::MIN_FREE_RATIO ? 'ok' : 'degraded', 'detail' => round($ratio * 100, 1) . ' % libre'];
        }

        $statuses = array_column($checks, 'status');
        $status = in_array('fail', $statuses, true) ? 'fail' : (in_array('degraded', $statuses, true) ? 'degraded' : 'ok');

        return ['status' => $status, 'checks' => $checks];
    }
}
