<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Core\Config;
use App\Core\Http\IpUtils;
use App\Core\Validation\ValidationException;
use App\Core\Validation\Validator;
use App\Repositories\SmtpSettingsRepository;
use App\Services\Crypto;

/**
 * Configuration SMTP depuis l'interface : globale (plateforme) ou par projet. Le mot de passe
 * (souvent un mot de passe d'application) est chiffré ; un champ laissé vide conserve l'ancien.
 * Les hôtes de projet pointant vers une adresse privée sont refusés (SSRF).
 *
 * @phpstan-type SmtpConfig array{host: string, port: int, encryption: string, username: string, password: string, from_email: string, from_name: string, source: string}
 */
final class SmtpService
{
    public const array PRESETS = [
        'gmail' => ['label' => 'Gmail', 'host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls'],
        'outlook' => ['label' => 'Outlook / Office 365', 'host' => 'smtp.office365.com', 'port' => 587, 'encryption' => 'tls'],
        'yahoo' => ['label' => 'Yahoo', 'host' => 'smtp.mail.yahoo.com', 'port' => 465, 'encryption' => 'ssl'],
        'brevo' => ['label' => 'Brevo', 'host' => 'smtp-relay.brevo.com', 'port' => 587, 'encryption' => 'tls'],
    ];

    public function __construct(
        private readonly SmtpSettingsRepository $settings,
        private readonly Crypto $crypto,
        private readonly Config $config,
    ) {
    }

    /**
     * Configuration effective : celle du projet, sinon la globale (interface), sinon config/env.php.
     *
     * @return SmtpConfig|null
     */
    public function resolve(?int $projectId): ?array
    {
        if ($projectId !== null && ($row = $this->settings->get($projectId)) !== null) {
            return $this->decrypt($row, 'project');
        }
        if (($row = $this->settings->get(null)) !== null) {
            return $this->decrypt($row, 'platform');
        }
        $host = $this->config->string('mail.host');
        $username = $this->config->string('mail.username');
        if ($host === '' || ($username === '' && str_ends_with($host, 'gmail.com'))) {
            return null;
        }

        return [
            'host' => $host,
            'port' => $this->config->int('mail.port', 587),
            'encryption' => $this->config->string('mail.encryption', 'tls'),
            'username' => $username,
            'password' => $this->config->string('mail.password'),
            'from_email' => $this->config->string('mail.from_address') !== '' ? $this->config->string('mail.from_address') : $username,
            'from_name' => $this->config->string('mail.from_name', 'NovaBase'),
            'source' => 'env',
        ];
    }

    /**
     * Réglages enregistrés pour l'affichage (sans le mot de passe).
     *
     * @return array{host: string, port: int, encryption: string, username: string, from_email: string, from_name: string, has_password: bool, updated_at: string}|null
     */
    public function stored(?int $projectId): ?array
    {
        $row = $this->settings->get($projectId);

        return $row === null ? null : [
            'host' => $row['host'],
            'port' => $row['port'],
            'encryption' => $row['encryption'],
            'username' => $row['username'] ?? '',
            'from_email' => $row['from_email'],
            'from_name' => $row['from_name'],
            'has_password' => $row['password_enc'] !== null,
            'updated_at' => $row['updated_at'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @throws ValidationException
     */
    public function save(?int $projectId, array $input, bool $allowPrivateHost): void
    {
        $data = Validator::validate($input, [
            'host' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9][A-Za-z0-9.\-]{0,253}[A-Za-z0-9]$/'],
            'port' => 'required|integer|between:1,65535',
            'encryption' => 'required|in:tls,ssl,none',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'from_email' => 'nullable|email|max:254',
            'from_name' => 'nullable|string|max:120',
        ], ['host' => 'serveur SMTP', 'port' => 'port', 'encryption' => 'chiffrement', 'username' => 'identifiant', 'password' => 'mot de passe', 'from_email' => 'adresse d\'expédition', 'from_name' => 'nom d\'expéditeur']);

        $host = strtolower((string) $data['host']);
        if (!$allowPrivateHost) {
            $ips = gethostbynamel($host) ?: [];
            if ($ips === [] || array_filter($ips, IpUtils::isPrivateOrReserved(...)) !== []) {
                throw ValidationException::withMessage('host', 'Ce serveur est introuvable ou pointe vers une adresse privée.', ['host' => $host]);
            }
        }
        $username = is_string($data['username'] ?? null) ? $data['username'] : null;
        $fromEmail = is_string($data['from_email'] ?? null) ? $data['from_email'] : ($username !== null && Validator::isEmail($username) ? mb_strtolower($username) : null);
        if ($fromEmail === null) {
            throw ValidationException::withMessage('from_email', 'Indiquez l\'adresse d\'expédition (ou un identifiant qui est une adresse e-mail).', ['host' => $host]);
        }
        $password = is_string($data['password'] ?? null) ? $data['password'] : null;
        if ($password !== null && str_ends_with($host, 'gmail.com')) {
            // Les mots de passe d'application Google s'affichent par groupes de 4 caractères.
            $password = str_replace(' ', '', $password);
        }
        $existing = $this->settings->get($projectId);

        $this->settings->save($projectId, [
            'host' => $host,
            'port' => (int) $data['port'],
            'encryption' => (string) $data['encryption'],
            'username' => $username,
            'password_enc' => $password !== null ? $this->crypto->encrypt($password, 'smtp_password') : ($existing['password_enc'] ?? null),
            'from_email' => $fromEmail,
            'from_name' => is_string($data['from_name'] ?? null) ? $data['from_name'] : 'NovaBase',
        ]);
    }

    public function delete(?int $projectId): void
    {
        $this->settings->delete($projectId);
    }

    /**
     * @param array{host: string, port: int, encryption: string, username: string|null, password_enc: string|null, from_email: string, from_name: string, updated_at: string} $row
     *
     * @return SmtpConfig
     */
    private function decrypt(array $row, string $source): array
    {
        return [
            'host' => $row['host'],
            'port' => $row['port'],
            'encryption' => $row['encryption'],
            'username' => $row['username'] ?? '',
            'password' => $row['password_enc'] !== null ? $this->crypto->decrypt($row['password_enc'], 'smtp_password') : '',
            'from_email' => $row['from_email'],
            'from_name' => $row['from_name'],
            'source' => $source,
        ];
    }
}
