<?php

declare(strict_types=1);

namespace App\Services\Data;

use App\Core\Database\Database;
use App\Core\Database\Identifier;
use App\Core\Database\LockTimeoutException;
use App\Core\Database\SqlSplitter;
use App\Core\Http\HttpException;
use App\Core\Validation\ValidationException;
use App\Repositories\TableRepository;
use App\Services\Projects\ProjectProvisioner;
use App\Services\UsageService;

/**
 * Éditeur SQL de la console. Les requêtes sont exécutées avec l'utilisateur MySQL du projet :
 * ce sont ses droits (limités à sa propre base) qui font la sécurité. Le service ajoute des
 * limites (taille, nombre d'instructions, durée, lignes affichées), journalise les instructions
 * qui modifient la base et resynchronise l'API après une modification de structure.
 *
 * @phpstan-import-type ProjectRow from \App\Repositories\ProjectRepository
 * @phpstan-import-type SyncReport from SchemaSync
 *
 * @phpstan-type StatementResult array{sql: string, status: string, kind: string, columns: list<string>, rows: list<list<string|null>>, truncated: bool, affected: int|null, insert_id: string|null, error: string|null, duration_ms: float}
 */
final class SqlEditorService
{
    public const int MAX_BYTES = 100_000;

    public const int MAX_STATEMENTS = 50;

    public const int MAX_ROWS = 500;

    /** Durée maximale d'une lecture (millisecondes, max_execution_time de MySQL). */
    public const int MAX_SELECT_MS = 15_000;

    private const array READ_KEYWORDS = ['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN', 'TABLE', 'VALUES', 'WITH', 'CHECKSUM'];

    /** Changement de base, verrous et procédures : inutiles ici et sources de blocages. */
    private const array FORBIDDEN_KEYWORDS = ['USE', 'LOCK', 'UNLOCK', 'LOAD', 'HANDLER', 'PREPARE', 'EXECUTE', 'DEALLOCATE', 'DELIMITER', 'START', 'BEGIN', 'COMMIT', 'ROLLBACK', 'XA'];

    private const array SCHEMA_KEYWORDS = ['CREATE', 'ALTER', 'DROP', 'RENAME'];

    /** Instructions qui consomment de l'espace (refusées si le quota de stockage est épuisé). */
    private const array GROWTH_KEYWORDS = ['INSERT', 'UPDATE', 'REPLACE', 'CREATE', 'ALTER'];

    /** Modèles proposés dans l'éditeur. */
    public const array SNIPPETS = [
        'Créer une table' => "CREATE TABLE produits (\n  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n  nom VARCHAR(120) NOT NULL,\n  prix DECIMAL(10,2) NOT NULL DEFAULT 0,\n  stock INT NOT NULL DEFAULT 0,\n  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP\n);",
        'Insérer' => "INSERT INTO produits (nom, prix, stock) VALUES\n  ('Café arabica', 1500, 24),\n  ('Thé vert', 1200, 40);",
        'Lire' => 'SELECT * FROM produits ORDER BY id DESC LIMIT 50;',
        'Modifier' => 'UPDATE produits SET stock = stock - 1 WHERE id = 1;',
        'Supprimer' => 'DELETE FROM produits WHERE id = 1;',
        'Tables' => 'SHOW TABLES;',
        'Structure' => 'DESCRIBE produits;',
    ];

    public function __construct(
        private readonly ProjectProvisioner $provisioner,
        private readonly SchemaSync $sync,
        private readonly TableRepository $tables,
        private readonly UsageService $usage,
        private readonly Database $platform,
    ) {
    }

    /**
     * @param ProjectRow $project
     *
     * @return array{results: list<StatementResult>, sync: SyncReport|null, duration_ms: float}
     *
     * @throws ValidationException
     */
    public function execute(array $project, int $actorId, string $sql): array
    {
        if (trim($sql) === '') {
            throw ValidationException::withMessage('sql', 'Saisissez au moins une requête.', ['sql' => $sql]);
        }
        if (strlen($sql) > self::MAX_BYTES) {
            throw ValidationException::withMessage('sql', 'Script trop long (100 Ko maximum).', ['sql' => '']);
        }
        $statements = SqlSplitter::split($sql);
        if ($statements === []) {
            throw ValidationException::withMessage('sql', 'Aucune requête à exécuter (seulement des commentaires ?).', ['sql' => $sql]);
        }
        if (count($statements) > self::MAX_STATEMENTS) {
            throw ValidationException::withMessage('sql', 'Trop d\'instructions (' . self::MAX_STATEMENTS . ' maximum par exécution).', ['sql' => $sql]);
        }
        foreach ($statements as $statement) {
            // Fonctions qui bloqueraient la connexion (les commentaires sont déjà retirés par SqlSplitter).
            if (preg_match('/\b(SLEEP|BENCHMARK|GET_LOCK)\s*\(/i', self::withoutStrings($statement)) === 1) {
                throw ValidationException::withMessage('sql', 'SLEEP, BENCHMARK et GET_LOCK ne sont pas autorisés dans l\'éditeur.', ['sql' => $sql]);
            }
        }
        $keywords = array_map(self::keyword(...), $statements);
        if (array_intersect($keywords, self::FORBIDDEN_KEYWORDS) !== []) {
            throw ValidationException::withMessage('sql', 'USE, LOCK, LOAD, HANDLER, PREPARE, DELIMITER et les transactions manuelles ne sont pas disponibles : l\'éditeur travaille toujours sur la base de ce projet.', ['sql' => $sql]);
        }
        if (array_intersect($keywords, self::GROWTH_KEYWORDS) !== []) {
            try {
                $this->usage->assertStorageAvailable($project);
            } catch (HttpException $e) {
                throw ValidationException::withMessage('sql', $e->getMessage() . ' Les lectures et suppressions restent possibles.', ['sql' => $sql]);
            }
        }

        $started = hrtime(true);

        try {
            $outcome = $this->runLocked($project, $actorId, $statements, $keywords);
        } catch (LockTimeoutException) {
            throw ValidationException::withMessage('sql', 'Une autre modification de ce projet est en cours : réessayez dans quelques secondes.', ['sql' => $sql]);
        }

        return [...$outcome, 'duration_ms' => round((hrtime(true) - $started) / 1e6, 1)];
    }

    /**
     * @param ProjectRow   $project
     * @param list<string> $statements
     * @param list<string> $keywords
     *
     * @return array{results: list<StatementResult>, sync: SyncReport|null}
     *
     * @throws LockTimeoutException
     */
    private function runLocked(array $project, int $actorId, array $statements, array $keywords): array
    {
        return $this->platform->withLock('schema:' . $project['ref'], function () use ($project, $actorId, $statements, $keywords): array {
            $db = $this->provisioner->connect($project);
            $pdo = $db->pdo();
            $pdo->setAttribute(class_exists(\Pdo\Mysql::class) ? \Pdo\Mysql::ATTR_USE_BUFFERED_QUERY : \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
            $pdo->exec('SET SESSION max_execution_time = ' . self::MAX_SELECT_MS);

            $results = [];
            $schemaChanged = false;
            $failed = false;
            foreach ($statements as $i => $statement) {
                if ($failed) {
                    $results[] = self::result($statement, 'skipped', 0.0);
                    continue;
                }
                $result = $this->run($pdo, $statement);
                $results[] = $result;
                $failed = $result['status'] === 'error';
                if (!in_array($keywords[$i], self::READ_KEYWORDS, true)) {
                    $this->tables->logChange($project['id'], $actorId, 'sql_editor', mb_substr($statement, 0, 60_000), !$failed, $result['error']);
                }
                $schemaChanged = $schemaChanged || in_array($keywords[$i], self::SCHEMA_KEYWORDS, true);
            }

            $pdo->setAttribute(class_exists(\Pdo\Mysql::class) ? \Pdo\Mysql::ATTR_USE_BUFFERED_QUERY : \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

            return ['results' => $results, 'sync' => $schemaChanged ? $this->sync->sync($project, $db, $actorId) : null];
        }, 15);
    }

    /**
     * Requête de lecture proposée pour chaque table (liste latérale de l'éditeur).
     *
     * @param list<string> $tables noms validés par Identifier::problem()
     *
     * @return array<string, string>
     */
    public static function selectSnippets(array $tables): array
    {
        $out = [];
        foreach ($tables as $table) {
            // sql-identifier-validated : nom issu des définitions de tables, quoté.
            $out[$table] = 'SELECT * FROM ' . Identifier::quote($table) . ' LIMIT 50;';
        }

        return $out;
    }

    /**
     * Exécute une instruction et met en forme son résultat (lignes ou nombre de lignes affectées).
     *
     * @return StatementResult
     */
    private function run(\PDO $pdo, string $statement): array
    {
        $started = hrtime(true);
        try {
            // Requête saisie par le propriétaire du projet, exécutée avec les droits de l'utilisateur MySQL du projet.
            $stmt = $pdo->query($statement);
            if ($stmt === false) {
                throw new \PDOException('Requête refusée.');
            }
            $result = self::result($statement, 'ok', 0.0);
            if ($stmt->columnCount() > 0) {
                $result['kind'] = 'rows';
                for ($c = 0; $c < $stmt->columnCount(); ++$c) {
                    $meta = $stmt->getColumnMeta($c);
                    $result['columns'][] = is_array($meta) && is_string($meta['name'] ?? null) ? $meta['name'] : 'col' . ($c + 1);
                }
                while (($row = $stmt->fetch(\PDO::FETCH_NUM)) !== false) {
                    if (count($result['rows']) >= self::MAX_ROWS) {
                        $result['truncated'] = true;
                        break;
                    }
                    $result['rows'][] = array_map(self::display(...), is_array($row) ? array_values($row) : []);
                }
            } else {
                $result['affected'] = $stmt->rowCount();
                $id = $pdo->lastInsertId();
                $result['insert_id'] = is_string($id) && $id !== '0' && self::keyword($statement) === 'INSERT' ? $id : null;
            }
            $stmt->closeCursor();
        } catch (\PDOException $e) {
            $result = self::result($statement, 'error', 0.0);
            $result['error'] = self::message($e);
        }
        $result['duration_ms'] = round((hrtime(true) - $started) / 1e6, 2);

        return $result;
    }

    /**
     * @return StatementResult
     */
    private static function result(string $statement, string $status, float $duration): array
    {
        return ['sql' => $statement, 'status' => $status, 'kind' => 'command', 'columns' => [], 'rows' => [], 'truncated' => false, 'affected' => null, 'insert_id' => null, 'error' => null, 'duration_ms' => $duration];
    }

    /** Premier mot-clé de l'instruction, en majuscules. */
    public static function keyword(string $statement): string
    {
        return preg_match('/^\(*\s*([A-Za-z]+)/', ltrim($statement), $m) === 1 ? strtoupper($m[1]) : '';
    }

    private static function display(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        $text = is_scalar($value) ? (string) $value : '';
        if (!mb_check_encoding($text, 'UTF-8')) {
            return '0x' . strtoupper(bin2hex(substr($text, 0, 32))) . (strlen($text) > 32 ? '…' : '');
        }

        return mb_strlen($text) > 500 ? mb_substr($text, 0, 500) . '…' : $text;
    }

    /** Message MySQL lisible : « [1146] Table 'proj_x.t' doesn't exist ». */
    private static function message(\PDOException $e): string
    {
        $info = $e->errorInfo;
        if (is_array($info) && isset($info[1], $info[2])) {
            return '[' . (int) $info[1] . '] ' . (string) $info[2];
        }

        return $e->getMessage();
    }

    private static function withoutStrings(string $sql): string
    {
        return (string) preg_replace('/\'(?:[^\'\\\\]|\\\\.|\'\')*\'|"(?:[^"\\\\]|\\\\.|"")*"|`[^`]*`/s', "''", $sql);
    }
}
