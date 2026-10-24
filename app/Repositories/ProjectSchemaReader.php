<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * Lecture de la structure réelle d'une base de projet (information_schema), avec la connexion
 * de l'utilisateur MySQL du projet. Le nom de la base est toujours explicite (jamais DATABASE() :
 * un « USE » exécuté sur la connexion changerait la base courante).
 *
 * @phpstan-type PhysicalColumn array{name: string, data_type: string, column_type: string, nullable: bool, default: string|null, key: string, extra: string, max_length: int|null}
 * @phpstan-type PhysicalIndex array{name: string, unique: bool, columns: list<string>}
 * @phpstan-type PhysicalForeignKey array{column: string, table: string, referenced: string, on_delete: string}
 * @phpstan-type PhysicalTable array{name: string, columns: list<PhysicalColumn>, indexes: list<PhysicalIndex>, foreign_keys: list<PhysicalForeignKey>}
 */
final class ProjectSchemaReader
{
    /** @return array<string, PhysicalTable> */
    public function read(Database $db, string $schema): array
    {
        $tables = [];
        foreach ($db->select(
            'SELECT c.TABLE_NAME AS t, c.COLUMN_NAME AS name, c.DATA_TYPE AS data_type, c.COLUMN_TYPE AS column_type, c.IS_NULLABLE AS nullable,
                    c.COLUMN_DEFAULT AS dflt, c.COLUMN_KEY AS col_key, c.EXTRA AS extra, c.CHARACTER_MAXIMUM_LENGTH AS max_length
             FROM information_schema.COLUMNS c
             JOIN information_schema.TABLES t ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME
             WHERE c.TABLE_SCHEMA = ? AND t.TABLE_TYPE = \'BASE TABLE\'
             ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION',
            [$schema],
        ) as $c) {
            $name = (string) $c['t'];
            $tables[$name] ??= ['name' => $name, 'columns' => [], 'indexes' => [], 'foreign_keys' => []];
            $tables[$name]['columns'][] = [
                'name' => (string) $c['name'],
                'data_type' => strtolower((string) $c['data_type']),
                'column_type' => strtolower((string) $c['column_type']),
                'nullable' => $c['nullable'] === 'YES',
                'default' => $c['dflt'] !== null ? (string) $c['dflt'] : null,
                'key' => (string) $c['col_key'],
                'extra' => strtolower((string) $c['extra']),
                'max_length' => $c['max_length'] !== null ? (int) $c['max_length'] : null,
            ];
        }

        $indexes = [];
        foreach ($db->select(
            'SELECT TABLE_NAME AS t, INDEX_NAME AS name, NON_UNIQUE AS non_unique, COLUMN_NAME AS col
             FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX',
            [$schema],
        ) as $i) {
            $key = $i['t'] . "\0" . $i['name'];
            $indexes[$key] ??= ['table' => (string) $i['t'], 'name' => (string) $i['name'], 'unique' => (int) $i['non_unique'] === 0, 'columns' => []];
            $indexes[$key]['columns'][] = (string) $i['col'];
        }
        foreach ($indexes as $index) {
            if (isset($tables[$index['table']])) {
                $tables[$index['table']]['indexes'][] = ['name' => $index['name'], 'unique' => $index['unique'], 'columns' => $index['columns']];
            }
        }

        // Clés étrangères mono-colonne uniquement (les clés composites ne sont pas exposées par l'API).
        foreach ($db->select(
            'SELECT k.TABLE_NAME AS t, MIN(k.COLUMN_NAME) AS col, MIN(k.REFERENCED_TABLE_NAME) AS ref_table, MIN(k.REFERENCED_COLUMN_NAME) AS ref_col, MIN(r.DELETE_RULE) AS on_delete
             FROM information_schema.KEY_COLUMN_USAGE k
             JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME
             WHERE k.TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL
             GROUP BY k.TABLE_NAME, k.CONSTRAINT_NAME HAVING COUNT(*) = 1',
            [$schema],
        ) as $f) {
            if (isset($tables[(string) $f['t']])) {
                $tables[(string) $f['t']]['foreign_keys'][] = ['column' => (string) $f['col'], 'table' => (string) $f['ref_table'], 'referenced' => (string) $f['ref_col'], 'on_delete' => (string) $f['on_delete']];
            }
        }

        return $tables;
    }
}
