<?php

declare(strict_types=1);

namespace App\Services\Data;

use App\Core\Database\Database;
use App\Core\Database\Identifier;
use App\Repositories\ProjectSchemaReader;
use App\Repositories\TableRepository;
use App\Repositories\UserRepository;
use App\Services\QuotaService;

/**
 * Aligne les définitions de tables (utilisées par l'API et la console) sur la structure MySQL
 * réelle d'un projet, après des requêtes de l'éditeur SQL. Une table est exposée si sa clé
 * primaire est une colonne `id` auto-incrémentée ou CHAR(36) ; les colonnes système absentes
 * (owner_id, created_at, updated_at) sont ajoutées. À appeler sous le verrou de schéma du projet.
 *
 * @phpstan-import-type ProjectRow from \App\Repositories\ProjectRepository
 * @phpstan-import-type ColumnDef from TableRepository
 * @phpstan-import-type PhysicalTable from ProjectSchemaReader
 * @phpstan-import-type PhysicalColumn from ProjectSchemaReader
 *
 * @phpstan-type SyncReport array{registered: list<string>, updated: list<string>, removed: list<string>, ignored: array<string, string>}
 */
final class SchemaSync
{
    /** Colonnes système ajoutées si absentes (DDL constant). */
    private const array SYSTEM_DDL = [
        'owner_id' => 'ADD COLUMN `owner_id` BIGINT UNSIGNED NULL, ADD KEY `idx_owner_id` (`owner_id`)',
        'created_at' => 'ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ];

    private const array INTEGER_TYPES = ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'];

    public function __construct(
        private readonly TableRepository $tables,
        private readonly ProjectSchemaReader $reader,
        private readonly QuotaService $quotas,
        private readonly UserRepository $users,
    ) {
    }

    /**
     * @param ProjectRow $project
     *
     * @return SyncReport
     */
    public function sync(array $project, Database $db, ?int $actorId): array
    {
        $report = ['registered' => [], 'updated' => [], 'removed' => [], 'ignored' => []];
        $physical = $this->reader->read($db, $project['db_name']);
        $existing = array_column($this->tables->forProject($project['id']), null, 'name');
        $owner = $this->users->findById($project['owner_id']);
        $limit = $owner !== null ? $this->quotas->limit($owner, 'tables_per_project') : null;
        $count = count(array_intersect_key($existing, $physical));

        foreach ($physical as $name => $table) {
            if (($problem = Identifier::problem($name)) !== null) {
                $report['ignored'][$name] = 'nom non utilisable par l\'API. ' . $problem;
                continue;
            }
            [$pkType, $reason] = self::primaryKey($table);
            if ($pkType === null) {
                $report['ignored'][$name] = $reason;
                continue;
            }
            if (!isset($existing[$name]) && $limit !== null && $count >= $limit) {
                $report['ignored'][$name] = 'limite de ' . $limit . ' tables par projet atteinte pour votre offre.';
                continue;
            }
            $missing = array_diff_key(self::SYSTEM_DDL, array_flip(array_column($table['columns'], 'name')));
            if ($missing !== []) {
                $sql = 'ALTER TABLE ' . Identifier::quote($name) . ' ' . implode(', ', $missing);
                try {
                    $db->pdo()->exec($sql);
                    $this->tables->logChange($project['id'], $actorId, 'sync_system_columns', $sql, true, null);
                } catch (\PDOException $e) {
                    $this->tables->logChange($project['id'], $actorId, 'sync_system_columns', $sql, false, $e->getMessage());
                    $report['ignored'][$name] = 'impossible d\'ajouter les colonnes système (' . implode(', ', array_keys($missing)) . ').';
                    continue;
                }
            }

            $columns = self::columns($table, $physical);
            if (isset($existing[$name])) {
                if ($existing[$name]['pk_type'] !== $pkType || $existing[$name]['columns'] !== $columns) {
                    $this->tables->replaceStructure($existing[$name]['id'], $pkType, $columns);
                    $report['updated'][] = $name;
                }
            } else {
                $this->tables->create($project['id'], $name, $pkType, TableRepository::DEFAULT_RULES, $columns);
                $report['registered'][] = $name;
                ++$count;
            }
        }

        foreach ($existing as $name => $definition) {
            if (!isset($physical[$name]) || isset($report['ignored'][$name])) {
                $this->tables->delete($definition['id']);
                $report['removed'][] = $name;
            }
        }

        return $report;
    }

    /**
     * Type de clé primaire exposable : [pk_type, null] ou [null, raison].
     *
     * @param PhysicalTable $table
     *
     * @return array{0: string|null, 1: string}
     */
    private static function primaryKey(array $table): array
    {
        $primary = null;
        foreach ($table['indexes'] as $index) {
            if ($index['name'] === 'PRIMARY') {
                $primary = $index['columns'];
            }
        }
        if ($primary !== ['id']) {
            return [null, 'il faut une clé primaire sur une seule colonne nommée « id ».'];
        }
        foreach ($table['columns'] as $column) {
            if ($column['name'] !== 'id') {
                continue;
            }
            if (in_array($column['data_type'], self::INTEGER_TYPES, true) && str_contains($column['extra'], 'auto_increment')) {
                return ['bigint', ''];
            }
            if (in_array($column['data_type'], ['char', 'varchar'], true) && $column['max_length'] === 36) {
                return ['uuid', ''];
            }
        }

        return [null, 'la colonne « id » doit être un entier AUTO_INCREMENT ou un CHAR(36) (UUID).'];
    }

    /**
     * Colonnes exposées (hors colonnes système), dans l'ordre de la table.
     *
     * @param PhysicalTable                $table
     * @param array<string, PhysicalTable> $physical
     *
     * @return list<ColumnDef>
     */
    private static function columns(array $table, array $physical): array
    {
        $foreignKeys = array_column($table['foreign_keys'], null, 'column');
        $unique = [];
        $indexed = [];
        foreach ($table['indexes'] as $index) {
            if ($index['name'] === 'PRIMARY') {
                continue;
            }
            if ($index['unique'] && count($index['columns']) === 1) {
                $unique[$index['columns'][0]] = true;
            } elseif (!$index['unique']) {
                $indexed[$index['columns'][0]] = true;
            }
        }

        $out = [];
        foreach ($table['columns'] as $column) {
            $name = $column['name'];
            if (in_array($name, ColumnTypes::SYSTEM_COLUMNS, true)
                || preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $name) !== 1
                || str_contains($column['extra'], 'virtual generated')
                || str_contains($column['extra'], 'stored generated')) {
                continue;
            }
            $fk = $foreignKeys[$name] ?? null;
            $fkTable = null;
            $onDelete = null;
            if ($fk !== null && $fk['referenced'] === 'id' && isset($physical[$fk['table']])) {
                [$type, $length] = ['foreign', null];
                $fkTable = $fk['table'];
                $onDelete = in_array($fk['on_delete'], ['CASCADE', 'SET NULL'], true) ? $fk['on_delete'] : 'RESTRICT';
            } else {
                $logical = self::logicalType($column);
                if ($logical === null) {
                    continue;
                }
                [$type, $length] = $logical;
            }
            $default = str_contains($column['extra'], 'auto_increment') ? 'AUTO_INCREMENT' : $column['default'];

            $out[] = [
                'name' => $name,
                'type' => $type,
                'length' => $length,
                'nullable' => $column['nullable'],
                'is_unique' => isset($unique[$name]),
                'is_indexed' => isset($indexed[$name]) || $fkTable !== null,
                'default_value' => $default !== null ? mb_substr($default, 0, 255) : null,
                'fk_table' => $fkTable,
                'fk_on_delete' => $onDelete,
            ];
        }

        return $out;
    }

    /**
     * Type logique NovaBase d'une colonne MySQL, ou null si le type n'est pas exposé par l'API.
     *
     * @param PhysicalColumn $column
     *
     * @return array{0: string, 1: int|null}|null
     */
    private static function logicalType(array $column): ?array
    {
        $max = $column['max_length'];

        return match ($column['data_type']) {
            'char' => $max === 36 ? ['uuid', null] : ['string', min(255, $max ?? 255)],
            'varchar' => $max !== null && $max <= 255 ? ['string', $max] : ['text', null],
            'tinytext', 'text', 'mediumtext', 'longtext' => ['text', null],
            'enum', 'set', 'time', 'year' => ['string', 255],
            'tinyint' => str_starts_with($column['column_type'], 'tinyint(1)') ? ['boolean', null] : ['integer', null],
            'smallint', 'mediumint', 'int', 'integer' => ['integer', null],
            'bigint' => ['bigint', null],
            'decimal', 'numeric' => ['decimal', null],
            'float', 'double', 'real' => ['float', null],
            'date' => ['date', null],
            'datetime', 'timestamp' => ['datetime', null],
            'json' => ['json', null],
            default => null,
        };
    }
}
