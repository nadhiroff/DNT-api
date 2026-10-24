<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * Définitions des tables des projets (métadonnées du Table Editor).
 *
 * @phpstan-type ColumnDef array{name: string, type: string, length: int|null, nullable: bool, is_unique: bool, is_indexed: bool, default_value: string|null, fk_table: string|null, fk_on_delete: string|null}
 * @phpstan-type AccessRules array{read: string, insert: string, update: string, delete: string}
 * @phpstan-type TableDef array{id: int, name: string, pk_type: string, access_rules: AccessRules, created_at: string, columns: list<ColumnDef>}
 */
final class TableRepository
{
    public const array RULES = ['public' => 'Public', 'authenticated' => 'Utilisateurs connectés', 'owner' => 'Propriétaire uniquement', 'secret' => 'Clé secrète uniquement'];

    public const array DEFAULT_RULES = ['read' => 'public', 'insert' => 'secret', 'update' => 'secret', 'delete' => 'secret'];

    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<TableDef> */
    public function forProject(int $projectId): array
    {
        $tables = $this->db->select('SELECT id, name, pk_type, access_rules, created_at FROM table_definitions WHERE project_id = ? ORDER BY name', [$projectId]);
        if ($tables === []) {
            return [];
        }
        $ids = array_map(static fn (array $t): int => (int) $t['id'], $tables);
        $columns = [];
        foreach ($this->db->select(
            'SELECT table_id, name, type, length, nullable, is_unique, is_indexed, default_value, fk_table, fk_on_delete FROM column_definitions WHERE table_id IN (' . Database::placeholders($ids) . ') ORDER BY position, id',
            $ids,
        ) as $c) {
            $columns[(int) $c['table_id']][] = self::column($c);
        }

        return array_map(static fn (array $t): array => self::table($t, $columns[(int) $t['id']] ?? []), $tables);
    }

    /** @return TableDef|null */
    public function find(int $projectId, string $name): ?array
    {
        foreach ($this->forProject($projectId) as $table) {
            if ($table['name'] === $name) {
                return $table;
            }
        }

        return null;
    }

    public function count(int $projectId): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM table_definitions WHERE project_id = ?', [$projectId]);
    }

    /**
     * @param AccessRules     $rules
     * @param list<ColumnDef> $columns
     */
    public function create(int $projectId, string $name, string $pkType, array $rules, array $columns): int
    {
        return $this->db->transaction(function (Database $db) use ($projectId, $name, $pkType, $rules, $columns): int {
            $id = $db->insert(
                'INSERT INTO table_definitions (project_id, name, pk_type, access_rules) VALUES (?, ?, ?, ?)',
                [$projectId, $name, $pkType, json_encode($rules, JSON_THROW_ON_ERROR)],
            );
            foreach ($columns as $position => $column) {
                $this->insertColumn($db, $id, $column, $position);
            }

            return $id;
        });
    }

    /**
     * @param ColumnDef $column
     */
    public function addColumn(int $tableId, array $column): void
    {
        $position = (int) $this->db->scalar('SELECT COALESCE(MAX(position), -1) + 1 FROM column_definitions WHERE table_id = ?', [$tableId]);
        $this->insertColumn($this->db, $tableId, $column, $position);
    }

    public function dropColumn(int $tableId, string $name): void
    {
        $this->db->execute('DELETE FROM column_definitions WHERE table_id = ? AND name = ?', [$tableId, $name]);
    }

    public function rename(int $projectId, int $tableId, string $old, string $new): void
    {
        $this->db->transaction(function (Database $db) use ($projectId, $tableId, $old, $new): void {
            $db->execute('UPDATE table_definitions SET name = ? WHERE id = ?', [$new, $tableId]);
            $db->execute(
                'UPDATE column_definitions c JOIN table_definitions t ON t.id = c.table_id SET c.fk_table = ? WHERE t.project_id = ? AND c.fk_table = ?',
                [$new, $projectId, $old],
            );
        });
    }

    /**
     * @param AccessRules $rules
     */
    public function updateRules(int $tableId, array $rules): void
    {
        $this->db->execute('UPDATE table_definitions SET access_rules = ? WHERE id = ?', [json_encode($rules, JSON_THROW_ON_ERROR), $tableId]);
    }

    public function delete(int $tableId): void
    {
        $this->db->execute('DELETE FROM table_definitions WHERE id = ?', [$tableId]);
    }

    /**
     * Tables du projet qui référencent $table par une clé étrangère.
     *
     * @return list<string> « table.colonne »
     */
    public function referencing(int $projectId, string $table): array
    {
        return array_map(static fn (array $r): string => $r['t'] . '.' . $r['c'], $this->db->select(
            'SELECT t.name AS t, c.name AS c FROM column_definitions c JOIN table_definitions t ON t.id = c.table_id WHERE t.project_id = ? AND c.fk_table = ?',
            [$projectId, $table],
        ));
    }

    public function logChange(int $projectId, ?int $userId, string $action, string $sql, bool $ok, ?string $error): void
    {
        $this->db->execute(
            'INSERT INTO schema_changes (project_id, user_id, action, sql_executed, status, error) VALUES (?, ?, ?, ?, ?, ?)',
            [$projectId, $userId, $action, $sql, $ok ? 'success' : 'failed', $error !== null ? mb_substr($error, 0, 500) : null],
        );
    }

    /**
     * @return list<array{action: string, sql_executed: string, status: string, error: string|null, created_at: string}>
     */
    public function changes(int $projectId, int $limit = 30): array
    {
        return array_map(static fn (array $r): array => [
            'action' => (string) $r['action'],
            'sql_executed' => (string) $r['sql_executed'],
            'status' => (string) $r['status'],
            'error' => $r['error'] !== null ? (string) $r['error'] : null,
            'created_at' => (string) $r['created_at'],
        ], $this->db->select('SELECT action, sql_executed, status, error, created_at FROM schema_changes WHERE project_id = ? ORDER BY id DESC LIMIT ?', [$projectId, $limit]));
    }

    /**
     * @param ColumnDef $column
     */
    private function insertColumn(Database $db, int $tableId, array $column, int $position): void
    {
        $db->execute(
            'INSERT INTO column_definitions (table_id, name, type, length, nullable, is_unique, is_indexed, default_value, fk_table, fk_on_delete, position)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$tableId, $column['name'], $column['type'], $column['length'], $column['nullable'], $column['is_unique'], $column['is_indexed'], $column['default_value'], $column['fk_table'], $column['fk_on_delete'], $position],
        );
    }

    /**
     * @param array<string, mixed> $c
     *
     * @return ColumnDef
     */
    private static function column(array $c): array
    {
        return [
            'name' => (string) $c['name'],
            'type' => (string) $c['type'],
            'length' => $c['length'] !== null ? (int) $c['length'] : null,
            'nullable' => (bool) $c['nullable'],
            'is_unique' => (bool) $c['is_unique'],
            'is_indexed' => (bool) $c['is_indexed'],
            'default_value' => $c['default_value'] !== null ? (string) $c['default_value'] : null,
            'fk_table' => $c['fk_table'] !== null ? (string) $c['fk_table'] : null,
            'fk_on_delete' => $c['fk_on_delete'] !== null ? (string) $c['fk_on_delete'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $t
     * @param list<ColumnDef>      $columns
     *
     * @return TableDef
     */
    private static function table(array $t, array $columns): array
    {
        $rules = is_string($t['access_rules']) ? json_decode($t['access_rules'], true) : null;
        $rules = is_array($rules) ? $rules : [];
        $clean = [];
        foreach (self::DEFAULT_RULES as $op => $default) {
            $value = $rules[$op] ?? $default;
            $clean[$op] = is_string($value) && isset(self::RULES[$value]) ? $value : $default;
        }

        return [
            'id' => (int) $t['id'],
            'name' => (string) $t['name'],
            'pk_type' => (string) $t['pk_type'],
            'access_rules' => $clean,
            'created_at' => (string) $t['created_at'],
            'columns' => $columns,
        ];
    }
}
