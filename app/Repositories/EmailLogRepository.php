<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * Historique des e-mails envoyés par l'API des projets.
 *
 * @phpstan-type EmailLogRow array{id: int, recipients: string, subject: string, status: string, error: string|null, attempts: int, sent_at: string|null, created_at: string}
 */
final class EmailLogRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function create(int $projectId, ?int $keyId, string $recipients, string $subject): int
    {
        return $this->db->insert(
            'INSERT INTO email_logs (project_id, api_key_id, recipients, subject) VALUES (?, ?, ?, ?)',
            [$projectId, $keyId, mb_substr($recipients, 0, 1000), mb_substr($subject, 0, 200)],
        );
    }

    public function markSent(int $id): void
    {
        $this->db->execute("UPDATE email_logs SET status = 'sent', error = NULL, attempts = attempts + 1, sent_at = UTC_TIMESTAMP() WHERE id = ?", [$id]);
    }

    public function markFailed(int $id, string $error): void
    {
        $this->db->execute("UPDATE email_logs SET status = 'failed', error = ?, attempts = attempts + 1 WHERE id = ?", [mb_substr($error, 0, 1000), $id]);
    }

    /** @return EmailLogRow|null */
    public function find(int $projectId, int $id): ?array
    {
        $row = $this->db->selectOne('SELECT id, recipients, subject, status, error, attempts, sent_at, created_at FROM email_logs WHERE id = ? AND project_id = ?', [$id, $projectId]);

        return $row !== null ? self::hydrate($row) : null;
    }

    /** @return list<EmailLogRow> */
    public function latest(int $projectId, string $status, int $limit = 50): array
    {
        return array_map(self::hydrate(...), $this->db->select(
            "SELECT id, recipients, subject, status, error, attempts, sent_at, created_at FROM email_logs WHERE project_id = ? AND (? = '' OR status = ?) ORDER BY id DESC LIMIT ?",
            [$projectId, $status, $status, $limit],
        ));
    }

    /**
     * @param array<string, mixed> $r
     *
     * @return EmailLogRow
     */
    private static function hydrate(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'recipients' => (string) $r['recipients'],
            'subject' => (string) $r['subject'],
            'status' => (string) $r['status'],
            'error' => $r['error'] !== null ? (string) $r['error'] : null,
            'attempts' => (int) $r['attempts'],
            'sent_at' => $r['sent_at'] !== null ? (string) $r['sent_at'] : null,
            'created_at' => (string) $r['created_at'],
        ];
    }
}
