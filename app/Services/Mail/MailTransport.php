<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Core\Config;
use App\Core\Log\Logger;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

/**
 * Envoi effectif : SMTP (PHPMailer, configuration du projet → globale → config/env.php)
 * ou « log » (fichiers dans storage/mail, pour le développement).
 */
final class MailTransport
{
    public function __construct(
        private readonly Config $config,
        private readonly string $mailDirectory,
        private readonly ?SmtpService $smtp = null,
    ) {
    }

    public function send(MailMessage $message, ?int $projectId = null): void
    {
        if ($this->config->string('mail.driver', 'smtp') === 'log') {
            $this->writeToFile($message);

            return;
        }
        $settings = $this->smtp?->resolve($projectId);
        if ($settings === null) {
            throw new RuntimeException('Aucun serveur SMTP configuré : renseignez-le dans Administration → E-mails.');
        }
        $this->sendSmtp($message, $settings);
    }

    /**
     * @param array{host: string, port: int, encryption: string, username: string, password: string, from_email: string, from_name: string} $settings
     */
    private function sendSmtp(MailMessage $message, array $settings): void
    {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $settings['host'];
        $mailer->Port = $settings['port'];
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->Timeout = 15;
        $mailer->SMTPSecure = match ($settings['encryption']) {
            'ssl' => PHPMailer::ENCRYPTION_SMTPS,
            'tls' => PHPMailer::ENCRYPTION_STARTTLS,
            default => '',
        };
        $mailer->SMTPAutoTLS = $settings['encryption'] !== 'none';
        if ($settings['username'] !== '') {
            $mailer->SMTPAuth = true;
            $mailer->Username = $settings['username'];
            $mailer->Password = $settings['password'];
        }
        $mailer->setFrom($settings['from_email'], $settings['from_name']);
        foreach ($message->recipients() as $to) {
            $mailer->addAddress($to);
        }
        foreach ($message->cc as $cc) {
            $mailer->addCC($cc);
        }
        foreach ($message->bcc as $bcc) {
            $mailer->addBCC($bcc);
        }
        foreach ($message->attachments as $file) {
            $mailer->addStringAttachment((string) base64_decode($file['content'], true), $file['filename'], PHPMailer::ENCODING_BASE64, $file['type']);
        }
        $mailer->Subject = $message->subject;
        $mailer->isHTML($message->html !== '');
        $mailer->Body = $message->html !== '' ? $message->html : $message->text;
        $mailer->AltBody = $message->text;
        $mailer->send();
    }

    private function writeToFile(MailMessage $message): void
    {
        if (!is_dir($this->mailDirectory)) {
            mkdir($this->mailDirectory, 0750, true);
        }
        // Horodatage à la microseconde : l'ordre alphabétique des fichiers est l'ordre d'envoi.
        $now = \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', microtime(true))) ?: new \DateTimeImmutable();
        $file = $this->mailDirectory . '/' . $now->format('Ymd-His-u') . bin2hex(random_bytes(2)) . '.mail.php';
        $data = $message->toArray();
        $record = json_encode(['to' => implode(', ', $data['to'])] + $data + ['sent_at' => gmdate('c')], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($file, Logger::GUARD . $record, LOCK_EX) === false) {
            throw new RuntimeException('Impossible d\'écrire le message dans ' . $this->mailDirectory);
        }
    }
}
