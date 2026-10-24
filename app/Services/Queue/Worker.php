<?php

declare(strict_types=1);

namespace App\Services\Queue;

use App\Core\Container;
use App\Core\Log\Logger;
use App\Jobs\JobInterface;
use App\Repositories\JobRepository;
use Throwable;

/**
 * Exécute les tâches de la file : nouvelles tentatives avec délai croissant (1 min, 5 min, 15 min),
 * puis déplacement dans failed_jobs.
 */
final class Worker
{
    private const array BACKOFF = [60, 300, 900];

    private const int STALE_SECONDS = 900;

    private string $id;

    public function __construct(
        private readonly JobRepository $jobs,
        private readonly Container $container,
        private readonly Logger $logger,
    ) {
        $this->id = substr((gethostname() ?: 'worker') . ':' . getmypid() . ':' . bin2hex(random_bytes(3)), 0, 64);
    }

    /**
     * Traite une tâche. Retourne false si la file est vide.
     */
    public function runNext(string $queue = 'default'): bool
    {
        $job = $this->jobs->reserve($queue, $this->id, self::STALE_SECONDS);
        if ($job === null) {
            return false;
        }
        try {
            $type = $job['type'];
            if (!is_subclass_of($type, JobInterface::class)) {
                throw new \RuntimeException('Type de tâche non autorisé : ' . $type);
            }
            $payload = json_decode($job['payload'], true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                throw new \RuntimeException('Payload invalide.');
            }
            /** @var array<string, mixed> $payload */
            $this->container->get($type)->handle($payload);
            $this->jobs->delete($job['id']);
            $this->logger->info('Tâche exécutée', ['job_id' => $job['id'], 'type' => $type]);
        } catch (Throwable $e) {
            $message = $e::class . ': ' . $e->getMessage();
            if ($job['attempts'] >= $job['max_attempts']) {
                $this->jobs->fail($job, $message);
                $this->logger->error('Tâche abandonnée', ['job_id' => $job['id'], 'type' => $job['type'], 'exception' => $e]);
            } else {
                $delay = self::BACKOFF[min($job['attempts'] - 1, count(self::BACKOFF) - 1)];
                $this->jobs->release($job['id'], $delay, $message);
                $this->logger->warning('Tâche reportée', ['job_id' => $job['id'], 'type' => $job['type'], 'retry_in' => $delay, 'error' => $e->getMessage()]);
            }
        }

        return true;
    }
}
