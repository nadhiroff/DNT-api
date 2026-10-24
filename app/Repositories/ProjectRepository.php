<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * Métadonnées des projets (les données des clients vivent dans les bases proj_<ref>).
 *
 * @phpstan-type ProjectRow array{id: int, ref: string, owner_id: int, name: string, db_name: string, db_user: string, db_pass_enc: string, jwt_secret_enc: string, status: string, created_at: string}
 */
final class ProjectRepository
{
    private const string COLUMNS = 'p.id, p.ref, p.owner_id, p.name, p.db_name, p.db_user, p.db_pass_enc, p.jwt_secret_enc, p.status, p.created_at';

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array{ref: string, owner_id: int, name: string, db_name: string, db_user: string, db_pass_enc: string, jwt_secret_enc: string} $data
     */
    public function create(array $data): int
    {
        return $this->db->insert(
            "INSERT INTO projects (ref, owner_id, name, db_name, db_user, db_pass_enc, jwt_secret_enc, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'provisioning')",
            [$data['ref'], $data['owner_id'], $data['name'], $data['db_name'], $data['db_user'], $data['db_pass_enc'], $data['jwt_secret_enc']],
        );
    }

    /** @return ProjectRow|null */
    public function findByRef(string $ref): ?array
    {
        $row = $this->db->selectOne('SELECT ' . self::COLUMNS . ' FROM projects p WHERE p.ref = ?', [$ref]);

        return $row !== null ? self::hydrate($row) : null;
    }

    /** @return ProjectRow|null */
    public function findById(int $id): ?array
    {
        $row = $this->db->selectOne('SELECT ' . self::COLUMNS . ' FROM projects p WHERE p.id = ?', [$id]);

        return $row !== null ? self::hydrate($row) : null;
    }

    /** @return list<ProjectRow> */
    public function forOwner(int $ownerId): array
    {
        return array_map(self::hydrate(...), $this->db->select(
            "SELECT " . self::COLUMNS . " FROM projects p WHERE p.owner_id = ? AND p.status <> 'failed' ORDER BY p.created_at DESC LIMIT 200",
            [$ownerId],
        ));
    }

    /**
     * Tous les projets avec leur propriétaire (panneau superadmin).
     *
     * @return list<array{id: int, ref: string, name: string, status: string, db_name: string, created_at: string, owner_email: string, owner_name: string}>
     */
    public function allWithOwner(string $search, int $limit, int $offset): array
    {
        $rows = $this->db->select(
            "SELECT p.id, p.ref, p.name, p.status, p.db_name, p.created_at, u.email AS owner_email, u.name AS owner_name
             FROM projects p JOIN users u ON u.id = p.owner_id
             WHERE p.status <> 'failed' AND (? = '' OR p.name LIKE ? OR p.ref LIKE ? OR u.email LIKE ?)
             ORDER BY p.created_at DESC LIMIT ? OFFSET ?",
            [$search, '%' . $search . '%', '%' . $search . '%', '%' . $search . '%', $limit, $offset],
        );

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'ref' => (string) $r['ref'],
            'name' => (string) $r['name'],
            'status' => (string) $r['status'],
            'db_name' => (string) $r['db_name'],
            'created_at' => (string) $r['created_at'],
            'owner_email' => (string) $r['owner_email'],
            'owner_name' => (string) $r['owner_name'],
        ], $rows);
    }

    public function countForOwner(int $ownerId): int
    {
        return (int) $this->db->scalar("SELECT COUNT(*) FROM projects WHERE owner_id = ? AND status <> 'failed'", [$ownerId]);
    }

    public function countAll(): int
    {
        return (int) $this->db->scalar("SELECT COUNT(*) FROM projects WHERE status <> 'failed'");
    }

    public function setStatus(int $id, string $status): void
    {
        $this->db->execute('UPDATE projects SET status = ? WHERE id = ?', [$status, $id]);
    }

    public function rename(int $id, string $name): void
    {
        $this->db->execute('UPDATE projects SET name = ? WHERE id = ?', [$name, $id]);
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM projects WHERE id = ?', [$id]);
    }

    /**
     * Origines CORS autorisées (liste vide = toutes) ; null si le projet n'existe pas.
     *
     * @return list<string>|null
     */
    public function corsOrigins(string $ref): ?array
    {
        $row = $this->db->selectOne('SELECT cors_origins FROM projects WHERE ref = ?', [$ref]);
        if ($row === null) {
            return null;
        }
        $origins = is_string($row['cors_origins']) ? json_decode($row['cors_origins'], true) : [];

        return is_array($origins) ? array_values(array_filter($origins, 'is_string')) : [];
    }

    /**
     * @param list<string> $origins
     */
    public function setCorsOrigins(int $id, array $origins): void
    {
        $this->db->execute('UPDATE projects SET cors_origins = ? WHERE id = ?', [json_encode($origins, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), $id]);
    }

    public function refExists(string $ref): bool
    {
        return $this->db->scalar('SELECT 1 FROM projects WHERE ref = ?', [$ref]) !== null;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return ProjectRow
     */
    private static function hydrate(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'ref' => (string) $row['ref'],
            'owner_id' => (int) $row['owner_id'],
            'name' => (string) $row['name'],
            'db_name' => (string) $row['db_name'],
            'db_user' => (string) $row['db_user'],
            'db_pass_enc' => (string) $row['db_pass_enc'],
            'jwt_secret_enc' => (string) $row['jwt_secret_enc'],
            'status' => (string) $row['status'],
            'created_at' => (string) $row['created_at'],
        ];
    }
}
