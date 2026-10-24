<?php

declare(strict_types=1);

namespace App\Services\Queue;

use App\Core\Config;
use App\Core\Container;
use App\Core\Log\Logger;
use App\Core\Session\Session;
use App\Jobs\JobInterface;
use App\Repositories\JobRepository;
use Throwable;

/**
 * Mise en file des tâches de fond. En production, toujours en base (exécutées par le worker,
 * jamais pendant la requête HTTP) ; le mode « sync » n'est accepté qu'en développement.
 */
final class Queue
{
    public function __construct(
        private readonly JobRepository $jobs,
        private readonly Config $config,
        private readonly Container $container,
        private readonly Logger $logger,
        private readonly Session $session,
    ) {
    }

    /**
     * @param class-string<JobInterface> $job
     * @param array<string, mixed>       $payload
     */
    public function dispatch(string $job, array $payload, string $queue = 'default', int $maxAttempts = 3): void
    {
        if (!is_subclass_of($job, JobInterface::class)) {
            throw new \InvalidArgumentException('Tâche inconnue : ' . $job);
        }
        if ($this->isSync()) {
            try {
                $this->container->get($job)->handle($payload);
            } catch (Throwable $e) {
                $this->logger->error('Échec de la tâche synchrone ' . $job, ['exception' => $e]);
                // Mode développement uniquement : l'erreur est affichée pour faciliter la configuration SMTP.
                if ($this->session->isStarted()) {
                    $this->session->flash('error', 'Envoi de l\'e-mail impossible : ' . mb_substr($e->getMessage(), 0, 300) . ' (message visible uniquement en développement)');
                }
            }

            return;
        }
        $this->jobs->push($queue, $job, json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $maxAttempts);
    }

    private function isSync(): bool
    {
        return $this->config->string('mail.queue_driver', 'database') === 'sync'
            && $this->config->string('app.env') !== 'production';
    }
}
