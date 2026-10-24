<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * Modèles d'e-mails personnalisés (project_id null = e-mails de la plateforme).
 *
 * @phpstan-type TemplateRow array{subject: string, heading: string, body: string, button_label: string, updated_at: string}
 */
final class EmailTemplateRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return TemplateRow|null */
    public function get(?int $projectId, string $key): ?array
    {
        $row = $projectId === null
            ? $this->db->selectOne('SELECT subject, heading, body, button_label, updated_at FROM email_templates WHERE project_id IS NULL AND template_key = ? ORDER BY id DESC LIMIT 1', [$key])
            : $this->db->selectOne('SELECT subject, heading, body, button_label, updated_at FROM email_templates WHERE project_id = ? AND template_key = ?', [$projectId, $key]);

        return $row === null ? null : [
            'subject' => (string) $row['subject'],
            'heading' => (string) $row['heading'],
            'body' => (string) $row['body'],
            'button_label' => (string) $row['button_label'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /** @return list<string> clés personnalisées */
    public function customizedKeys(?int $projectId): array
    {
        $rows = $projectId === null
            ? $this->db->select('SELECT template_key FROM email_templates WHERE project_id IS NULL')
            : $this->db->select('SELECT template_key FROM email_templates WHERE project_id = ?', [$projectId]);

        return array_map(static fn (array $r): string => (string) $r['template_key'], $rows);
    }

    /**
     * @param array{subject: string, heading: string, body: string, button_label: string} $data
     */
    public function save(?int $projectId, string $key, array $data): void
    {
        $this->db->transaction(function (Database $db) use ($projectId, $key, $data): void {
            $this->deleteWith($db, $projectId, $key);
            $db->execute(
                'INSERT INTO email_templates (project_id, template_key, subject, heading, body, button_label) VALUES (?, ?, ?, ?, ?, ?)',
                [$projectId, $key, $data['subject'], $data['heading'], $data['body'], $data['button_label']],
            );
        });
    }

    public function reset(?int $projectId, string $key): void
    {
        $this->deleteWith($this->db, $projectId, $key);
    }

    private function deleteWith(Database $db, ?int $projectId, string $key): void
    {
        $projectId === null
            ? $db->execute('DELETE FROM email_templates WHERE project_id IS NULL AND template_key = ?', [$key])
            : $db->execute('DELETE FROM email_templates WHERE project_id = ? AND template_key = ?', [$projectId, $key]);
    }
}
