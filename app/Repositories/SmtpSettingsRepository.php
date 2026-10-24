<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * Réglages SMTP : project_id null = configuration globale de la plateforme.
 *
 * @phpstan-type SmtpRow array{host: string, port: int, encryption: string, username: string|null, password_enc: string|null, from_email: string, from_name: string, updated_at: string}
 */
final class SmtpSettingsRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return SmtpRow|null */
    public function get(?int $projectId): ?array
    {
        $row = $projectId === null
            ? $this->db->selectOne('SELECT host, port, encryption, username, password_enc, from_email, from_name, updated_at FROM smtp_settings WHERE project_id IS NULL ORDER BY id DESC LIMIT 1')
            : $this->db->selectOne('SELECT host, port, encryption, username, password_enc, from_email, from_name, updated_at FROM smtp_settings WHERE project_id = ?', [$projectId]);
        if ($row === null) {
            return null;
        }

        return [
            'host' => (string) $row['host'],
            'port' => (int) $row['port'],
            'encryption' => (string) $row['encryption'],
            'username' => $row['username'] !== null ? (string) $row['username'] : null,
            'password_enc' => $row['password_enc'] !== null ? (string) $row['password_enc'] : null,
            'from_email' => (string) $row['from_email'],
            'from_name' => (string) $row['from_name'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /**
     * @param array{host: string, port: int, encryption: string, username: string|null, password_enc: string|null, from_email: string, from_name: string} $data
     */
    public function save(?int $projectId, array $data): void
    {
        $this->db->transaction(function (Database $db) use ($projectId, $data): void {
            // Une seule ligne par portée (globale ou projet).
            $projectId === null
                ? $db->execute('DELETE FROM smtp_settings WHERE project_id IS NULL')
                : $db->execute('DELETE FROM smtp_settings WHERE project_id = ?', [$projectId]);
            $db->execute(
                'INSERT INTO smtp_settings (project_id, host, port, encryption, username, password_enc, from_email, from_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$projectId, $data['host'], $data['port'], $data['encryption'], $data['username'], $data['password_enc'], $data['from_email'], $data['from_name']],
            );
        });
    }

    public function delete(?int $projectId): void
    {
        $projectId === null
            ? $this->db->execute('DELETE FROM smtp_settings WHERE project_id IS NULL')
            : $this->db->execute('DELETE FROM smtp_settings WHERE project_id = ?', [$projectId]);
    }
}
