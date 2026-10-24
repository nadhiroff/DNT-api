<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Core\Validation\Validator;
use InvalidArgumentException;

/**
 * Message validé : destinataires vérifiés, sujet sans retour à la ligne (anti-injection d'en-têtes),
 * pièces jointes en base64 avec liste noire d'extensions exécutables.
 */
final class MailMessage
{
    public const int MAX_RECIPIENTS = 50;

    public const int MAX_ATTACHMENTS_BYTES = 10_485_760;

    private const array BLOCKED_EXTENSIONS = ['exe', 'bat', 'cmd', 'com', 'msi', 'scr', 'js', 'vbs', 'ps1', 'sh', 'php', 'phtml', 'jar', 'apk', 'dll', 'hta', 'lnk'];

    /**
     * @param list<string>                                                $cc
     * @param list<string>                                                $bcc
     * @param list<array{filename: string, content: string, type: string}> $attachments contenu en base64
     * @param list<string>                                                $to
     */
    public function __construct(
        public readonly string|array $to,
        public readonly string $subject,
        public readonly string $html,
        public readonly string $text,
        public readonly array $cc = [],
        public readonly array $bcc = [],
        public readonly array $attachments = [],
    ) {
        $recipients = [...self::list($to), ...$cc, ...$bcc];
        if ($recipients === [] || count($recipients) > self::MAX_RECIPIENTS) {
            throw new InvalidArgumentException('Entre 1 et ' . self::MAX_RECIPIENTS . ' destinataires.');
        }
        foreach ($recipients as $address) {
            if (!Validator::isEmail($address)) {
                throw new InvalidArgumentException('Adresse invalide : ' . mb_substr($address, 0, 80));
            }
        }
        if (preg_match('/[\r\n\0]/', $subject) === 1 || mb_strlen($subject) > 200 || trim($subject) === '') {
            throw new InvalidArgumentException('Sujet invalide (une ligne, 200 caractères max).');
        }
        $total = 0;
        foreach ($attachments as $file) {
            $ext = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
            if (preg_match('/^[\pL\pN _.\-()]{1,120}$/u', $file['filename']) !== 1 || in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
                throw new InvalidArgumentException('Pièce jointe refusée : ' . mb_substr($file['filename'], 0, 80));
            }
            $decoded = base64_decode($file['content'], true);
            if ($decoded === false) {
                throw new InvalidArgumentException('Pièce jointe mal encodée (base64 attendu) : ' . $file['filename']);
            }
            $total += strlen($decoded);
        }
        if ($total > self::MAX_ATTACHMENTS_BYTES) {
            throw new InvalidArgumentException('Pièces jointes trop lourdes (10 Mo maximum au total).');
        }
    }

    /** @return list<string> */
    public function recipients(): array
    {
        return self::list($this->to);
    }

    /**
     * @return array{to: list<string>, subject: string, html: string, text: string, cc: list<string>, bcc: list<string>, attachments: list<array{filename: string, content: string, type: string}>}
     */
    public function toArray(): array
    {
        return ['to' => $this->recipients(), 'subject' => $this->subject, 'html' => $this->html, 'text' => $this->text, 'cc' => $this->cc, 'bcc' => $this->bcc, 'attachments' => $this->attachments];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        foreach (['subject', 'html', 'text'] as $key) {
            if (!is_string($data[$key] ?? null)) {
                throw new InvalidArgumentException('Message incomplet : ' . $key);
            }
        }
        $strings = static fn (mixed $v): array => is_array($v) ? array_values(array_filter($v, 'is_string')) : (is_string($v) ? [$v] : []);
        $attachments = [];
        foreach (is_array($data['attachments'] ?? null) ? $data['attachments'] : [] as $file) {
            if (is_array($file) && is_string($file['filename'] ?? null) && is_string($file['content'] ?? null)) {
                $attachments[] = ['filename' => $file['filename'], 'content' => $file['content'], 'type' => is_string($file['type'] ?? null) ? $file['type'] : 'application/octet-stream'];
            }
        }

        /** @var string $subject */
        $subject = $data['subject'];
        /** @var string $html */
        $html = $data['html'];
        /** @var string $text */
        $text = $data['text'];

        return new self($strings($data['to'] ?? []), $subject, $html, $text, $strings($data['cc'] ?? []), $strings($data['bcc'] ?? []), $attachments);
    }

    /**
     * @param string|list<string> $value
     *
     * @return list<string>
     */
    private static function list(string|array $value): array
    {
        return is_string($value) ? [$value] : array_values($value);
    }
}
