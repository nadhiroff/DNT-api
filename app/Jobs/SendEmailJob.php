<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Mail\MailMessage;
use App\Services\Mail\MailTransport;

/**
 * Envoi d'un e-mail de la plateforme (exécuté par le worker).
 */
final class SendEmailJob implements JobInterface
{
    public function __construct(private readonly MailTransport $transport)
    {
    }

    public function handle(array $payload): void
    {
        $this->transport->send(MailMessage::fromArray($payload));
    }
}
