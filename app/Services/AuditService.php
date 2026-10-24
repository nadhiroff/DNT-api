<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Log\Logger;
use App\Repositories\AuditLogRepository;

/**
 * Trace les actions sensibles (connexion, clés, suppressions, quotas, « se connecter en tant que »…).
 * Les métadonnées passent par le même filtre que les journaux : aucun secret n'est enregistré.
 */
final class AuditService
{
    public function __construct(
        private readonly AuditLogRepository $repository,
        private readonly Logger $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function record(string $action, ?int $actorId, ?string $target = null, ?string $ip = null, array $metadata = [], string $actorType = 'user'): void
    {
        if (preg_match('/^[a-z][a-z0-9_.]{2,63}$/', $action) !== 1) {
            throw new \InvalidArgumentException('Action d\'audit invalide : ' . $action);
        }
        /** @var array<string, mixed> $clean */
        $clean = Logger::sanitize($metadata);
        $this->repository->insert($actorId, $actorType, $action, $target, $ip, $clean);
        $this->logger->info('audit.' . $action, ['actor_id' => $actorId, 'target' => $target]);
    }
}
