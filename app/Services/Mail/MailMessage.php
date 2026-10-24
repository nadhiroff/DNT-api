<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Core\Validation\Validator;
use InvalidArgumentException;

/**
 * Message validé : destinataire unique vérifié, sujet sans retour à la ligne (anti-injection d'en-têtes).
 */
final class MailMessage
{
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $html,
        public readonly string $text,
    ) {
        if (!Validator::isEmail($to)) {
            throw new InvalidArgumentException('Destinataire invalide.');
        }
        if (preg_match('/[\r\n\0]/', $subject) === 1 || mb_strlen($subject) > 200 || trim($subject) === '') {
            throw new InvalidArgumentException('Sujet invalide.');
        }
    }

    /** @return array{to: string, subject: string, html: string, text: string} */
    public function toArray(): array
    {
        return ['to' => $this->to, 'subject' => $this->subject, 'html' => $this->html, 'text' => $this->text];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        foreach (['to', 'subject', 'html', 'text'] as $key) {
            if (!is_string($data[$key] ?? null)) {
                throw new InvalidArgumentException('Message incomplet : ' . $key);
            }
        }

        /** @var array{to: string, subject: string, html: string, text: string} $data */
        return new self($data['to'], $data['subject'], $data['html'], $data['text']);
    }
}
