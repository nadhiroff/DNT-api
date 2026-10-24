<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Core\Config;
use App\Core\Log\Logger;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

/**
 * Envoi effectif : SMTP (PHPMailer) ou « log » (fichiers dans storage/mail, pour le développement).
 */
final class MailTransport
{
    public function __construct(
        private readonly Config $config,
        private readonly string $mailDirectory,
    ) {
    }

    public function send(MailMessage $message): void
    {
        match ($this->config->string('mail.driver', 'smtp')) {
            'log' => $this->writeToFile($message),
            default => $this->sendSmtp($message),
        };
    }

    private function sendSmtp(MailMessage $message): void
    {
        $host = $this->config->string('mail.host');
        if ($host === '' || $this->config->string('mail.username') === '' && $this->config->string('mail.password') === '' && str_ends_with($host, 'gmail.com')) {
            throw new RuntimeException('SMTP non configuré : renseignez MAIL_HOST, MAIL_USERNAME et MAIL_PASSWORD dans config/env.php.');
        }
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = $host;
        $mailer->Port = $this->config->int('mail.port', 587);
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $mailer->Timeout = 15;
        $encryption = $this->config->string('mail.encryption', 'tls');
        $mailer->SMTPSecure = match ($encryption) {
            'ssl' => PHPMailer::ENCRYPTION_SMTPS,
            'tls' => PHPMailer::ENCRYPTION_STARTTLS,
            default => '',
        };
        $mailer->SMTPAutoTLS = $encryption !== 'none';
        $username = $this->config->string('mail.username');
        $password = $this->config->string('mail.password');
        if (str_ends_with(strtolower($host), 'gmail.com')) {
            // Les mots de passe d'application Google s'affichent par groupes de 4 : les espaces ne font pas partie du secret.
            $password = str_replace(' ', '', $password);
        }
        if ($username !== '') {
            $mailer->SMTPAuth = true;
            $mailer->Username = $username;
            $mailer->Password = $password;
        }
        $from = $this->config->string('mail.from_address');
        if ($from === '' && $username !== '') {
            $from = $username;
        }
        if ($from === '') {
            throw new RuntimeException('Adresse d\'expéditeur manquante (MAIL_FROM_ADDRESS ou MAIL_USERNAME).');
        }
        $mailer->setFrom($from, $this->config->string('mail.from_name', 'NovaBase'));
        $mailer->addAddress($message->to);
        $mailer->Subject = $message->subject;
        $mailer->isHTML(true);
        $mailer->Body = $message->html;
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
        $record = json_encode($message->toArray() + ['sent_at' => gmdate('c')], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($file, Logger::GUARD . $record, LOCK_EX) === false) {
            throw new RuntimeException('Impossible d\'écrire le message dans ' . $this->mailDirectory);
        }
    }
}
