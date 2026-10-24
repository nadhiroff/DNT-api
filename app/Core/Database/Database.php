<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;
use PDOStatement;
use Throwable;

/**
 * Enveloppe fine autour de PDO : uniquement des requêtes préparées avec paramètres liés.
 * Aucune méthode n'accepte de fragment SQL construit à partir de données utilisateur.
 */
final class Database
{
    private int $transactionDepth = 0;

    private ?PDO $pdo;

    /** @var (\Closure(): PDO)|null */
    private ?\Closure $connector;

    /**
     * @param PDO|\Closure(): PDO $connection connexion, ou fabrique appelée à la première requête
     */
    public function __construct(PDO|\Closure $connection)
    {
        if ($connection instanceof PDO) {
            $this->pdo = $connection;
            $this->connector = null;
        } else {
            $this->pdo = null;
            $this->connector = $connection;
        }
    }

    /**
     * Connexion paresseuse : aucune connexion n'est ouverte tant qu'aucune requête n'est exécutée.
     */
    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $connector = $this->connector;
            if ($connector === null) {
                throw new \LogicException('Connexion indisponible.');
            }
            $this->pdo = $connector();
        }

        return $this->pdo;
    }

    /**
     * Ferme la connexion (elle sera rouverte à la prochaine requête si une fabrique est disponible).
     */
    public function disconnect(): void
    {
        if ($this->connector !== null && $this->transactionDepth === 0) {
            $this->pdo = null;
        }
    }

    /**
     * @param array<int|string, mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $params = []): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->run($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /**
     * @param array<int|string, mixed> $params
     *
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch(PDO::FETCH_ASSOC);

        /** @var array<string, mixed>|false $row */
        return $row === false ? null : $row;
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public function scalar(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * Exécute une requête d'écriture et retourne le nombre de lignes affectées.
     *
     * @param array<int|string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /**
     * Exécute un INSERT et retourne l'identifiant auto-incrémenté.
     *
     * @param array<int|string, mixed> $params
     */
    public function insert(string $sql, array $params = []): int
    {
        $this->run($sql, $params);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Exécute $callback dans une transaction (imbrication gérée par SAVEPOINT).
     * Attention : le DDL MySQL valide implicitement la transaction en cours.
     *
     * @template T
     *
     * @param callable(Database): T $callback
     *
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        if ($this->transactionDepth === 0) {
            $this->pdo()->beginTransaction();
        } else {
            $this->pdo()->exec('SAVEPOINT sp_' . $this->transactionDepth);
        }
        ++$this->transactionDepth;
        try {
            $result = $callback($this);
            --$this->transactionDepth;
            if ($this->transactionDepth === 0) {
                $this->pdo()->commit();
            } else {
                $this->pdo()->exec('RELEASE SAVEPOINT sp_' . $this->transactionDepth);
            }

            return $result;
        } catch (Throwable $e) {
            --$this->transactionDepth;
            if ($this->transactionDepth === 0) {
                if ($this->pdo()->inTransaction()) {
                    $this->pdo()->rollBack();
                }
            } elseif ($this->pdo()->inTransaction()) {
                $this->pdo()->exec('ROLLBACK TO SAVEPOINT sp_' . $this->transactionDepth);
            }
            throw $e;
        }
    }

    /**
     * Verrou applicatif nommé (GET_LOCK) : sérialise des opérations entre processus/serveurs.
     */
    public function acquireLock(string $name, int $timeoutSeconds = 10): bool
    {
        return (int) $this->scalar('SELECT GET_LOCK(?, ?)', [self::lockName($name), $timeoutSeconds]) === 1;
    }

    public function releaseLock(string $name): void
    {
        $this->scalar('SELECT RELEASE_LOCK(?)', [self::lockName($name)]);
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function withLock(string $name, callable $callback, int $timeoutSeconds = 10): mixed
    {
        if (!$this->acquireLock($name, $timeoutSeconds)) {
            throw new LockTimeoutException('Impossible d\'obtenir le verrou « ' . $name . ' ».');
        }
        try {
            return $callback();
        } finally {
            $this->releaseLock($name);
        }
    }

    /**
     * Valeurs pour une clause IN : retourne « ?,?,? » à concaténer — uniquement des marqueurs, jamais des valeurs.
     *
     * @param array<mixed> $values
     */
    public static function placeholders(array $values): string
    {
        if ($values === []) {
            throw new \InvalidArgumentException('Une clause IN nécessite au moins une valeur.');
        }

        return implode(',', array_fill(0, count($values), '?'));
    }

    /**
     * @param array<int|string, mixed> $params
     */
    private function run(string $sql, array $params): PDOStatement
    {
        $statement = $this->pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $statement->bindValue($name, ...self::bindable($value));
        }
        $statement->execute();

        return $statement;
    }

    /**
     * @return array{0: mixed, 1: int}
     */
    private static function bindable(mixed $value): array
    {
        return match (true) {
            $value === null => [null, PDO::PARAM_NULL],
            is_int($value) => [$value, PDO::PARAM_INT],
            is_bool($value) => [$value ? 1 : 0, PDO::PARAM_INT],
            $value instanceof \DateTimeInterface => [$value->format('Y-m-d H:i:s'), PDO::PARAM_STR],
            $value instanceof \BackedEnum => [$value->value, is_int($value->value) ? PDO::PARAM_INT : PDO::PARAM_STR],
            is_float($value), is_string($value) => [(string) $value, PDO::PARAM_STR],
            default => throw new \InvalidArgumentException('Type de paramètre SQL non supporté : ' . get_debug_type($value)),
        };
    }

    private static function lockName(string $name): string
    {
        // GET_LOCK accepte 64 caractères maximum.
        return strlen($name) <= 64 ? $name : 'nb_' . substr(hash('sha256', $name), 0, 60);
    }
}
