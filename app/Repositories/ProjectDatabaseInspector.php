<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\DatabaseManager;

/**
 * Lecture d'information_schema avec le compte d'administration (le compte applicatif ne voit
 * pas les bases des projets).
 */
final class ProjectDatabaseInspector
{
    public function __construct(private readonly DatabaseManager $databases)
    {
    }

    /**
     * @return array{bytes: int, tables: list<array{name: string, rows: int, bytes: int, created_at: string|null}>}
     */
    public function describe(string $schema): array
    {
        $tables = $this->databases->admin()->select(
            'SELECT table_name AS name, table_rows AS row_count, data_length + index_length AS bytes, create_time AS created_at
             FROM information_schema.TABLES WHERE table_schema = ? ORDER BY table_name LIMIT 500',
            [$schema],
        );
        $list = array_map(static fn (array $t): array => [
            'name' => (string) $t['name'],
            'rows' => (int) ($t['row_count'] ?? 0),
            'bytes' => (int) ($t['bytes'] ?? 0),
            'created_at' => $t['created_at'] !== null ? (string) $t['created_at'] : null,
        ], $tables);

        return ['bytes' => array_sum(array_column($list, 'bytes')), 'tables' => $list];
    }

    /**
     * Taille totale de plusieurs bases.
     *
     * @param list<string> $schemas
     *
     * @return array<string, int>
     */
    public function sizes(array $schemas): array
    {
        if ($schemas === []) {
            return [];
        }
        $result = array_fill_keys($schemas, 0);
        foreach ($this->databases->admin()->select(
            'SELECT table_schema AS s, SUM(data_length + index_length) AS bytes FROM information_schema.TABLES WHERE table_schema IN (' . \App\Core\Database\Database::placeholders($schemas) . ') GROUP BY table_schema',
            $schemas,
        ) as $row) {
            $result[(string) $row['s']] = (int) $row['bytes'];
        }

        return $result;
    }

    /**
     * @return array{mysql_version: string, platform_bytes: int, projects_bytes: int}
     */
    public function overview(string $platformSchema): array
    {
        $row = $this->databases->admin()->selectOne(
            "SELECT VERSION() AS version,
                    (SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.TABLES WHERE table_schema = ?) AS platform_bytes,
                    (SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.TABLES WHERE table_schema LIKE 'proj\\_%') AS projects_bytes",
            [$platformSchema],
        ) ?? [];

        return [
            'mysql_version' => (string) ($row['version'] ?? ''),
            'platform_bytes' => (int) ($row['platform_bytes'] ?? 0),
            'projects_bytes' => (int) ($row['projects_bytes'] ?? 0),
        ];
    }
}
