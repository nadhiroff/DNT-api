<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\EmailLogRepository;
use App\Services\Mail\MailMessage;
use App\Services\Mail\MailTransport;
use Throwable;

/**
 * Envoi d'un e-mail demandé par l'API d'un projet (SMTP du projet, sinon celui de la plateforme)
 * et mise à jour de son historique.
 */
final class SendProjectEmailJob implements JobInterface
{
    public function __construct(
        private readonly MailTransport $transport,
        private readonly EmailLogRepository $logs,
    ) {
    }

    public function handle(array $payload): void
    {
        $logId = is_int($payload['log_id'] ?? null) ? $payload['log_id'] : 0;
        $projectId = is_int($payload['project_id'] ?? null) ? $payload['project_id'] : null;
        $message = is_array($payload['message'] ?? null) ? $payload['message'] : [];
        try {
            /** @var array<string, mixed> $message */
            $this->transport->send(MailMessage::fromArray($message), $projectId);
            $this->logs->markSent($logId);
        } catch (Throwable $e) {
            $this->logs->markFailed($logId, $e->getMessage());

            // Relancée : le worker retentera avec un délai croissant.
            throw $e;
        }
    }
}
