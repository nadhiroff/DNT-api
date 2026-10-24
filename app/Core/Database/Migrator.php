<?php

declare(strict_types=1);

namespace App\Core\Database;

use RuntimeException;

/**
 * Exécute les migrations SQL versionnées de database/migrations dans l'ordre lexical.
 * Chaque fichier n'est appliqué qu'une fois ; sa somme de contrôle est vérifiée ensuite.
 * Les migrations doivent être rejouables (IF NOT EXISTS, INSERT IGNORE…) car le DDL MySQL
 * n'est pas transactionnel.
 */
final class Migrator
{
    private const string LOCK = 'novabase_migrations';

    public function __construct(
        private readonly Database $db,
        private readonly string $directory,
    ) {
    }

    public function ensureTable(): void
    {
        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(191) NOT NULL,
                checksum CHAR(64) NOT NULL,
                batch INT UNSIGNED NOT NULL,
                duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_migrations_migration (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci',
        );
    }

    /** @return array<string, string> nom => chemin */
    public function files(): array
    {
        $files = glob($this->directory . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        $result = [];
        foreach ($files as $file) {
            $name = basename($file, '.sql');
            if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_[a-z0-9_]+$/', $name) !== 1) {
                throw new RuntimeException('Nom de migration invalide : ' . basename($file) . ' (attendu : AAAA_MM_JJ_HHMMSS_description.sql)');
            }
            $result[$name] = $file;
        }

        return $result;
    }

    /** @return array<string, array{checksum: string, batch: int, applied_at: string}> */
    public function applied(): array
    {
        $this->ensureTable();
        $applied = [];
        foreach ($this->db->select('SELECT migration, checksum, batch, applied_at FROM migrations ORDER BY id') as $row) {
            $applied[(string) $row['migration']] = [
                'checksum' => (string) $row['checksum'],
                'batch' => (int) $row['batch'],
                'applied_at' => (string) $row['applied_at'],
            ];
        }

        return $applied;
    }

    /**
     * @return list<array{migration: string, state: string}> state = applied | pending | modified
     */
    public function status(): array
    {
        $applied = $this->applied();
        $status = [];
        foreach ($this->files() as $name => $file) {
            $state = 'pending';
            if (isset($applied[$name])) {
                $state = hash_equals($applied[$name]['checksum'], self::checksum($file)) ? 'applied' : 'modified';
            }
            $status[] = ['migration' => $name, 'state' => $state];
        }

        return $status;
    }

    /**
     * Applique les migrations en attente.
     *
     * @param callable(string): void|null $onApplied
     *
     * @return list<string> migrations appliquées
     */
    public function migrate(?callable $onApplied = null): array
    {
        return $this->db->withLock(self::LOCK, function () use ($onApplied): array {
            $applied = $this->applied();
            foreach ($this->files() as $name => $file) {
                if (isset($applied[$name]) && !hash_equals($applied[$name]['checksum'], self::checksum($file))) {
                    throw new RuntimeException('La migration déjà appliquée « ' . $name . ' » a été modifiée. Créez une nouvelle migration au lieu de modifier une ancienne.');
                }
            }
            $batch = (int) $this->db->scalar('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations');
            $done = [];
            foreach ($this->files() as $name => $file) {
                if (isset($applied[$name])) {
                    continue;
                }
                $start = hrtime(true);
                $this->runFile($name, $file);
                $this->db->execute(
                    'INSERT INTO migrations (migration, checksum, batch, duration_ms) VALUES (?, ?, ?, ?)',
                    [$name, self::checksum($file), $batch, (int) ((hrtime(true) - $start) / 1_000_000)],
                );
                $done[] = $name;
                if ($onApplied !== null) {
                    $onApplied($name);
                }
            }

            return $done;
        }, 30);
    }

    private function runFile(string $name, string $file): void
    {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException('Lecture impossible : ' . $file);
        }
        foreach (SqlSplitter::split($sql) as $index => $statement) {
            try {
                $this->db->pdo()->exec($statement);
            } catch (\PDOException $e) {
                throw new RuntimeException(sprintf('Échec de la migration %s (instruction n°%d) : %s', $name, $index + 1, $e->getMessage()), 0, $e);
            }
        }
    }

    private static function checksum(string $file): string
    {
        $content = file_get_contents($file);

        // Les fins de ligne sont normalisées pour que le checksum soit stable entre OS.
        return hash('sha256', str_replace("\r\n", "\n", $content === false ? '' : $content));
    }
}
