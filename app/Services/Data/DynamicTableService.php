<?php

declare(strict_types=1);

namespace App\Services\Data;

use App\Core\Database\Database;
use App\Core\Database\Identifier;
use App\Core\Validation\ValidationException;
use App\Repositories\TableRepository;
use App\Services\Projects\ProjectProvisioner;
use App\Services\QuotaService;
use Throwable;

/**
 * Modifications de schéma des projets (Table Editor). Toujours exécutées avec l'utilisateur
 * MySQL du projet, sous verrou par projet, journalisées dans schema_changes. Le DDL MySQL
 * validant implicitement les transactions, chaque étape a sa compensation.
 *
 * @phpstan-import-type ProjectRow from \App\Repositories\ProjectRepository
 * @phpstan-import-type ColumnDef from TableRepository
 * @phpstan-import-type TableDef from TableRepository
 * @phpstan-import-type AccessRules from TableRepository
 */
final class DynamicTableService
{
    public function __construct(
        private readonly TableRepository $tables,
        private readonly ProjectProvisioner $provisioner,
        private readonly QuotaService $quotas,
        private readonly Database $platform,
    ) {
    }

    /**
     * @param ProjectRow $project
     *
     * @return list<TableDef>
     */
    public function tables(array $project): array
    {
        return $this->tables->forProject($project['id']);
    }

    /**
     * @param ProjectRow $project
     *
     * @return TableDef|null
     */
    public function table(array $project, string $name): ?array
    {
        return $this->tables->find($project['id'], $name);
    }

    /**
     * Valide la définition brute d'une colonne venant du formulaire ou de l'API.
     *
     * @param array<string, mixed> $raw
     * @param list<TableDef>       $existingTables
     *
     * @return ColumnDef
     *
     * @throws ValidationException
     */
    public function normalizeColumn(array $raw, array $existingTables, string $field = 'columns'): array
    {
        $name = is_string($raw['name'] ?? null) ? strtolower(trim($raw['name'])) : '';
        $type = is_string($raw['type'] ?? null) ? $raw['type'] : '';
        $fail = static fn (string $msg): ValidationException => ValidationException::withMessage($field, ($name !== '' ? 'Colonne « ' . $name . ' » : ' : '') . $msg);

        if (($problem = Identifier::problem($name)) !== null) {
            throw $fail($problem);
        }
        if (in_array($name, ColumnTypes::SYSTEM_COLUMNS, true)) {
            throw $fail('nom réservé (id, created_at, updated_at et owner_id sont ajoutés automatiquement).');
        }
        if (!isset(ColumnTypes::LABELS[$type])) {
            throw $fail('type inconnu.');
        }
        $length = null;
        if ($type === 'string') {
            $length = (int) ($raw['length'] ?? 255);
            if ($length < 1 || $length > 255) {
                throw $fail('longueur entre 1 et 255.');
            }
        }
        $nullable = in_array($raw['nullable'] ?? false, [true, '1', 1, 'on', 'true'], true);
        $fkTable = null;
        $onDelete = null;
        if ($type === 'foreign') {
            $fkTable = is_string($raw['fk_table'] ?? null) ? $raw['fk_table'] : '';
            if (!in_array($fkTable, array_column($existingTables, 'name'), true)) {
                throw $fail('table liée introuvable dans ce projet.');
            }
            $onDelete = is_string($raw['fk_on_delete'] ?? null) && isset(ColumnTypes::ON_DELETE[$raw['fk_on_delete']]) ? $raw['fk_on_delete'] : 'RESTRICT';
            if ($onDelete === 'SET NULL') {
                $nullable = true;
            }
        }
        [$default, $error] = ColumnTypes::defaultValue($type, $length, is_scalar($raw['default_value'] ?? null) ? trim((string) $raw['default_value']) : '');
        if ($error !== null) {
            throw $fail('valeur par défaut invalide (' . $error . ').');
        }

        return [
            'name' => $name,
            'type' => $type,
            'length' => $length,
            'nullable' => $nullable,
            'is_unique' => in_array($raw['is_unique'] ?? false, [true, '1', 1, 'on', 'true'], true),
            'is_indexed' => in_array($raw['is_indexed'] ?? false, [true, '1', 1, 'on', 'true'], true) || $type === 'foreign',
            'default_value' => $default,
            'fk_table' => $fkTable,
            'fk_on_delete' => $onDelete,
        ];
    }

    /**
     * @param ProjectRow              $project
     * @param array{id: int, role: string, plan_id: int|null} $owner
     * @param list<ColumnDef>         $columns
     *
     * @throws ValidationException
     */
    public function createTable(array $project, array $owner, int $actorId, string $name, string $pkType, array $columns): void
    {
        $name = strtolower(trim($name));
        if (($problem = Identifier::problem($name)) !== null) {
            throw ValidationException::withMessage('name', $problem, ['name' => $name]);
        }
        $pkType = $pkType === 'uuid' ? 'uuid' : 'bigint';
        $names = array_column($columns, 'name');
        if (count($names) !== count(array_unique($names))) {
            throw ValidationException::withMessage('columns', 'Deux colonnes portent le même nom.', ['name' => $name]);
        }

        $this->locked($project, function () use ($project, $owner, $actorId, $name, $pkType, $columns): void {
            $existing = $this->tables->forProject($project['id']);
            if (in_array($name, array_column($existing, 'name'), true)) {
                throw ValidationException::withMessage('name', 'Une table porte déjà ce nom.', ['name' => $name]);
            }
            $limit = $this->quotas->limit($owner, 'tables_per_project');
            if ($limit !== null && count($existing) >= $limit) {
                throw ValidationException::withMessage('name', 'Limite de ' . $limit . ' tables par projet atteinte pour votre offre.', ['name' => $name]);
            }
            $db = $this->provisioner->connect($project);
            $pdo = $db->pdo();
            $pkTypes = array_column($existing, 'pk_type', 'name');

            $lines = [$pkType === 'uuid' ? '`id` CHAR(36) NOT NULL' : '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT'];
            foreach ($columns as $column) {
                $lines[] = $this->columnSql($pdo, $column, $column['fk_table'] !== null ? ($pkTypes[$column['fk_table']] ?? 'bigint') : null);
            }
            $lines[] = '`owner_id` BIGINT UNSIGNED NULL';
            $lines[] = '`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP';
            $lines[] = '`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
            $lines[] = 'PRIMARY KEY (`id`)';
            $lines[] = 'KEY `idx_owner_id` (`owner_id`)';
            foreach ($columns as $column) {
                array_push($lines, ...$this->constraintSql($name, $column));
            }
            $sql = 'CREATE TABLE ' . Identifier::quote($name) . " (\n  " . implode(",\n  ", $lines) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci";

            $this->run($project, $actorId, 'create_table', $db, $sql);
            try {
                $this->tables->create($project['id'], $name, $pkType, TableRepository::DEFAULT_RULES, $columns);
            } catch (Throwable $e) {
                // Compensation : la table existe mais pas ses métadonnées.
                $db->pdo()->exec('DROP TABLE IF EXISTS ' . Identifier::quote($name));
                throw $e;
            }
        });
    }

    /**
     * @param ProjectRow $project
     * @param ColumnDef  $column
     *
     * @throws ValidationException
     */
    public function addColumn(array $project, int $actorId, string $tableName, array $column): void
    {
        $this->locked($project, function () use ($project, $actorId, $tableName, $column): void {
            $table = $this->require($project, $tableName);
            if (in_array($column['name'], array_column($table['columns'], 'name'), true)) {
                throw ValidationException::withMessage('columns', 'La colonne « ' . $column['name'] . ' » existe déjà.');
            }
            $db = $this->provisioner->connect($project);
            $refPk = $column['fk_table'] !== null ? ($this->tables->find($project['id'], $column['fk_table'])['pk_type'] ?? 'bigint') : null;
            $parts = ['ADD COLUMN ' . $this->columnSql($db->pdo(), $column, $refPk) . ' AFTER `id`'];
            foreach ($this->constraintSql($table['name'], $column) as $constraint) {
                $parts[] = 'ADD ' . $constraint;
            }
            // Une valeur obligatoire sans défaut est impossible sur une table qui contient déjà des lignes : MySQL le signalera.
            $this->run($project, $actorId, 'add_column', $db, 'ALTER TABLE ' . Identifier::quote($table['name']) . ' ' . implode(', ', $parts));
            $this->tables->addColumn($table['id'], $column);
        });
    }

    /**
     * @param ProjectRow $project
     */
    public function dropColumn(array $project, int $actorId, string $tableName, string $columnName): void
    {
        $this->locked($project, function () use ($project, $actorId, $tableName, $columnName): void {
            $table = $this->require($project, $tableName);
            $column = null;
            foreach ($table['columns'] as $c) {
                if ($c['name'] === $columnName) {
                    $column = $c;
                }
            }
            if ($column === null) {
                throw ValidationException::withMessage('columns', 'Colonne introuvable ou système (non supprimable).');
            }
            $db = $this->provisioner->connect($project);
            if ($column['fk_table'] !== null) {
                $this->run($project, $actorId, 'drop_foreign_key', $db, 'ALTER TABLE ' . Identifier::quote($table['name']) . ' DROP FOREIGN KEY ' . Identifier::quote(self::fkName($table['name'], $column['name'])));
            }
            $this->run($project, $actorId, 'drop_column', $db, 'ALTER TABLE ' . Identifier::quote($table['name']) . ' DROP COLUMN ' . Identifier::quote($column['name']));
            $this->tables->dropColumn($table['id'], $column['name']);
        });
    }

    /**
     * @param ProjectRow $project
     *
     * @throws ValidationException
     */
    public function renameTable(array $project, int $actorId, string $tableName, string $newName): void
    {
        $newName = strtolower(trim($newName));
        if (($problem = Identifier::problem($newName)) !== null) {
            throw ValidationException::withMessage('new_name', $problem);
        }
        $this->locked($project, function () use ($project, $actorId, $tableName, $newName): void {
            $table = $this->require($project, $tableName);
            if ($this->tables->find($project['id'], $newName) !== null) {
                throw ValidationException::withMessage('new_name', 'Une table porte déjà ce nom.');
            }
            $db = $this->provisioner->connect($project);
            $this->run($project, $actorId, 'rename_table', $db, 'RENAME TABLE ' . Identifier::quote($table['name']) . ' TO ' . Identifier::quote($newName));
            $this->tables->rename($project['id'], $table['id'], $table['name'], $newName);
        });
    }

    /**
     * @param ProjectRow $project
     *
     * @throws ValidationException
     */
    public function dropTable(array $project, int $actorId, string $tableName): void
    {
        $this->locked($project, function () use ($project, $actorId, $tableName): void {
            $table = $this->require($project, $tableName);
            $refs = array_values(array_filter($this->tables->referencing($project['id'], $table['name']), static fn (string $r): bool => !str_starts_with($r, $table['name'] . '.')));
            if ($refs !== []) {
                throw ValidationException::withMessage('confirm_table', 'Impossible : la table est référencée par ' . implode(', ', $refs) . '. Supprimez d\'abord ces colonnes.');
            }
            $db = $this->provisioner->connect($project);
            $this->run($project, $actorId, 'drop_table', $db, 'DROP TABLE ' . Identifier::quote($table['name']));
            $this->tables->delete($table['id']);
        });
    }

    /**
     * @param ProjectRow  $project
     * @param AccessRules $rules
     */
    public function updateRules(array $project, int $actorId, string $tableName, array $rules): void
    {
        $table = $this->require($project, $tableName);
        $clean = [];
        foreach (TableRepository::DEFAULT_RULES as $op => $default) {
            $clean[$op] = isset(TableRepository::RULES[$rules[$op]]) ? $rules[$op] : $default;
        }
        $this->tables->updateRules($table['id'], $clean);
        $this->tables->logChange($project['id'], $actorId, 'update_rules', json_encode($clean, JSON_THROW_ON_ERROR), true, null);
    }

    /**
     * @param ProjectRow $project
     *
     * @return TableDef
     */
    private function require(array $project, string $name): array
    {
        $table = $this->tables->find($project['id'], $name);
        if ($table === null) {
            throw new \App\Core\Http\HttpException(404, 'table_not_found', 'Table introuvable.');
        }

        return $table;
    }

    /**
     * @param ColumnDef $column
     */
    private function columnSql(\PDO $pdo, array $column, ?string $refPkType): string
    {
        $sql = Identifier::quote($column['name']) . ' ' . ColumnTypes::sql($column['type'], $column['length'], $refPkType)
            . ($column['nullable'] ? ' NULL' : ' NOT NULL');
        if ($column['default_value'] !== null) {
            // Valeur validée selon le type (ColumnTypes::defaultValue), puis échappée par le pilote.
            $sql .= ' DEFAULT ' . Identifier::literal($pdo, $column['default_value']);
        }

        return $sql;
    }

    /**
     * @param ColumnDef $column
     *
     * @return list<string>
     */
    private function constraintSql(string $table, array $column): array
    {
        $out = [];
        $col = Identifier::quote($column['name']);
        if ($column['is_unique']) {
            $out[] = 'UNIQUE KEY ' . Identifier::quote('uq_' . substr($column['name'], 0, 60)) . ' (' . $col . ')';
        } elseif ($column['is_indexed']) {
            $out[] = 'KEY ' . Identifier::quote('idx_' . substr($column['name'], 0, 59)) . ' (' . $col . ')';
        }
        if ($column['fk_table'] !== null && $column['fk_on_delete'] !== null && isset(ColumnTypes::ON_DELETE[$column['fk_on_delete']])) {
            $out[] = 'CONSTRAINT ' . Identifier::quote(self::fkName($table, $column['name'])) . ' FOREIGN KEY (' . $col . ') REFERENCES '
                . Identifier::quote($column['fk_table']) . ' (`id`) ON DELETE ' . $column['fk_on_delete'];
        }

        return $out;
    }

    private static function fkName(string $table, string $column): string
    {
        return 'fk_' . substr(hash('sha256', $table . '.' . $column), 0, 24);
    }

    /**
     * @param ProjectRow $project
     */
    private function run(array $project, int $actorId, string $action, Database $db, string $sql): void
    {
        try {
            $db->pdo()->exec($sql);
            $this->tables->logChange($project['id'], $actorId, $action, $sql, true, null);
        } catch (\PDOException $e) {
            $this->tables->logChange($project['id'], $actorId, $action, $sql, false, $e->getMessage());

            throw ValidationException::withMessage('columns', 'MySQL a refusé la modification : ' . self::friendly($e));
        }
    }

    private static function friendly(\PDOException $e): string
    {
        $info = $e->errorInfo;
        $code = is_array($info) && isset($info[1]) ? (int) $info[1] : 0;

        return match ($code) {
            1062 => 'des valeurs existantes sont en double (contrainte unique impossible).',
            1138, 1048 => 'des lignes existantes n\'ont pas de valeur pour une colonne obligatoire : ajoutez une valeur par défaut.',
            1215, 1822 => 'relation impossible (types incompatibles).',
            1451 => 'des lignes d\'autres tables dépendent de celle-ci.',
            1067 => 'valeur par défaut invalide.',
            default => 'erreur ' . $code . '.',
        };
    }

    /**
     * @param ProjectRow $project
     * @param callable(): void $callback
     */
    private function locked(array $project, callable $callback): void
    {
        $this->platform->withLock('schema:' . $project['ref'], $callback, 15);
    }
}
