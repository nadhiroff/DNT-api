<?php

declare(strict_types=1);

namespace App\Jobs;

/**
 * Tâche exécutée par le worker. Le payload est un tableau JSON-sérialisable.
 */
interface JobInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload): void;
}
