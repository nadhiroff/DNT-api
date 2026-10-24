<?php

declare(strict_types=1);

namespace App\Services\Data;

use App\Core\Database\Database;
use App\Core\Database\Identifier;
use App\Core\Http\HttpException;
use App\Repositories\TableRepository;
use App\Services\Projects\ProjectProvisioner;

/**
 * Lecture et écriture des données des projets (console et API REST).
 * Les identifiants viennent exclusivement des définitions de tables (liste blanche) et passent
 * par Identifier::quote() ; opérateurs et directions de tri sont mappés depuis des listes
 * blanches ; toutes les valeurs sont liées en paramètres.
 *
 * @phpstan-import-type ProjectRow from \App\Repositories\ProjectRepository
 * @phpstan-import-type TableDef from TableRepository
 */
final class ProjectDataService
{
    public const int MAX_LIMIT = 100;

    public const int DEFAULT_LIMIT = 20;

    public const int MAX_INSERT = 500;

    /** Paramètres de pagination et de forme : jamais des filtres. */
    public const array PAGING_PARAMS = ['select', 'order', 'limit', 'offset'];

    private const array OPERATORS = ['eq' => '=', 'neq' => '<>', 'gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<='];

    /** @var array<string, Database> */
    private array $connections = [];

    public function __construct(private readonly ProjectProvisioner $provisioner)
    {
    }

    /**
     * Colonnes de la table : système + définies, avec leur type logique.
     *
     * @param TableDef $table
     *
     * @return array<string, array{type: string, length: int|null, nullable: bool, writable: bool}>
     */
    public static function columns(array $table): array
    {
        $columns = ['id' => ['type' => $table['pk_type'] === 'uuid' ? 'uuid' : 'bigint', 'length' => null, 'nullable' => false, 'writable' => false]];
        foreach ($table['columns'] as $c) {
            $columns[$c['name']] = ['type' => $c['type'], 'length' => $c['length'], 'nullable' => $c['nullable'] || $c['default_value'] !== null, 'writable' => true];
        }
        $columns['owner_id'] = ['type' => 'bigint', 'length' => null, 'nullable' => true, 'writable' => false];
        $columns['created_at'] = ['type' => 'datetime', 'length' => null, 'nullable' => false, 'writable' => false];
        $columns['updated_at'] = ['type' => 'datetime', 'length' => null, 'nullable' => false, 'writable' => false];

        return $columns;
    }

    /**
     * Liste paginée avec filtres (« price=gte.1000 »), tri et sélection de colonnes.
     *
     * @param ProjectRow                     $project
     * @param TableDef                       $table
     * @param array<string, mixed>           $query  paramètres de la requête (select, order, limit, offset, filtres)
     * @param int|null                       $ownerId restreint aux lignes de ce propriétaire (règle « owner »)
     *
     * @return array{rows: list<array<string, mixed>>, total: int, limit: int, offset: int}
     */
    public function select(array $project, array $table, array $query, ?int $ownerId = null): array
    {
        $columns = self::columns($table);
        $selected = $this->selection($columns, $query['select'] ?? null);
        [$where, $params] = $this->filters($columns, $query, $ownerId);
        $order = $this->order($columns, $query['order'] ?? null);
        $limit = self::intParam($query['limit'] ?? null, self::DEFAULT_LIMIT, 1, self::MAX_LIMIT);
        $offset = self::intParam($query['offset'] ?? null, 0, 0, 10_000_000);

        $from = Identifier::quote($table['name']);
        $db = $this->db($project);
        // sql-identifier-validated : colonnes et table issues des définitions, quotées ; valeurs liées.
        $total = (int) $db->scalar('SELECT COUNT(*) FROM ' . $from . $where, $params);
        // sql-identifier-validated : idem (sélection, tri et pagination en liste blanche).
        $rows = $db->select('SELECT ' . $selected . ' FROM ' . $from . $where . $order . ' LIMIT ? OFFSET ?', [...$params, $limit, $offset]);

        return ['rows' => array_map(fn (array $r): array => $this->output($columns, $r), $rows), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /**
     * @param ProjectRow $project
     * @param TableDef   $table
     *
     * @return array<string, mixed>|null
     */
    public function find(array $project, array $table, string $id, ?int $ownerId = null): ?array
    {
        $columns = self::columns($table);
        [$idValue, $error] = ColumnTypes::cast(['type' => $columns['id']['type'], 'length' => null, 'nullable' => false], $id);
        if ($error !== null) {
            return null;
        }
        $sql = 'SELECT * FROM ' . Identifier::quote($table['name']) . ' WHERE `id` = ?' . ($ownerId !== null ? ' AND `owner_id` = ?' : '');
        $row = $this->db($project)->selectOne($sql, $ownerId !== null ? [$idValue, $ownerId] : [$idValue]);

        return $row !== null ? $this->output($columns, $row) : null;
    }

    /**
     * Insère une ou plusieurs lignes (max 500) dans une transaction. Retourne les lignes créées.
     *
     * @param ProjectRow                       $project
     * @param TableDef                         $table
     * @param list<array<string, mixed>>       $rows
     * @param array<string, mixed>             $system valeurs choisies pour les colonnes système (console
     *                                                 uniquement, validées par systemValues() ; jamais depuis l'API)
     *
     * @return list<array<string, mixed>>
     */
    public function insert(array $project, array $table, array $rows, ?int $ownerId = null, array $system = []): array
    {
        if ($rows === [] || count($rows) > self::MAX_INSERT) {
            throw new HttpException(400, 'bad_request', 'Entre 1 et ' . self::MAX_INSERT . ' lignes par insertion.');
        }
        $columns = self::columns($table);
        $db = $this->db($project);

        return $db->transaction(function (Database $db) use ($project, $table, $rows, $columns, $ownerId, $system): array {
            $created = [];
            foreach ($rows as $index => $row) {
                $values = [...$this->writable($columns, $row, false, count($rows) > 1 ? $index : null), ...$system];
                if ($table['pk_type'] === 'uuid' && !isset($values['id'])) {
                    $values['id'] = self::uuid();
                }
                if ($ownerId !== null) {
                    $values['owner_id'] = $ownerId;
                }
                $names = array_map(Identifier::quote(...), array_keys($values));
                $sql = 'INSERT INTO ' . Identifier::quote($table['name'])
                    . ($names === [] ? ' () VALUES ()' : ' (' . implode(', ', $names) . ') VALUES (' . Database::placeholders($values) . ')');
                $id = $this->guard(fn (): int => $db->insert($sql, array_values($values)));
                $created[] = $this->find($project, $table, (string) ($values['id'] ?? $id)) ?? [];
            }

            return $created;
        });
    }

    /**
     * Valeurs saisies dans la console pour les colonnes système (id, owner_id, created_at,
     * updated_at). Une valeur vide reste automatique. Retourne [valeurs, erreurs par colonne].
     *
     * @param TableDef             $table
     * @param array<string, mixed> $raw
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public static function systemValues(array $table, array $raw): array
    {
        $columns = self::columns($table);
        $values = [];
        $errors = [];
        foreach (ColumnTypes::SYSTEM_COLUMNS as $name) {
            $value = $raw[$name] ?? null;
            if (!is_string($value) || trim($value) === '') {
                continue;
            }
            [$cast, $error] = ColumnTypes::cast(['type' => $columns[$name]['type'], 'length' => null, 'nullable' => false], trim($value));
            if ($error !== null) {
                $errors[$name] = $error;
                continue;
            }
            $values[$name] = $cast;
        }

        return [$values, $errors];
    }

    /**
     * Mise à jour partielle (PATCH) ou complète (PUT).
     *
     * @param ProjectRow           $project
     * @param TableDef             $table
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>|null ligne mise à jour, null si introuvable
     */
    public function update(array $project, array $table, string $id, array $data, bool $partial, ?int $ownerId = null): ?array
    {
        $existing = $this->find($project, $table, $id, $ownerId);
        if ($existing === null) {
            return null;
        }
        $values = $this->writable(self::columns($table), $data, $partial, null);
        if ($values !== []) {
            $sets = implode(', ', array_map(static fn (string $c): string => Identifier::quote($c) . ' = ?', array_keys($values)));
            $sql = 'UPDATE ' . Identifier::quote($table['name']) . ' SET ' . $sets . ' WHERE `id` = ?';
            $this->guard(fn (): int => $this->db($project)->execute($sql, [...array_values($values), $existing['id']]));
        }

        return $this->find($project, $table, (string) $existing['id']);
    }

    /**
     * @param ProjectRow $project
     * @param TableDef   $table
     */
    public function delete(array $project, array $table, string $id, ?int $ownerId = null): bool
    {
        $existing = $this->find($project, $table, $id, $ownerId);
        if ($existing === null) {
            return false;
        }
        $this->guard(fn (): int => $this->db($project)->execute('DELETE FROM ' . Identifier::quote($table['name']) . ' WHERE `id` = ?', [$existing['id']]));

        return true;
    }

    /**
     * Suppression groupée des lignes qui correspondent aux filtres (mêmes opérateurs que la lecture).
     * Sans filtre, toute la table est vidée : l'API exige au moins un filtre avant d'appeler.
     *
     * @param ProjectRow           $project
     * @param TableDef             $table
     * @param array<string, mixed> $query
     *
     * @return int nombre de lignes supprimées
     */
    public function deleteWhere(array $project, array $table, array $query, ?int $ownerId = null): int
    {
        [$where, $params] = $this->filters(self::columns($table), $query, $ownerId);
        $sql = 'DELETE FROM ' . Identifier::quote($table['name']) . $where;

        return $this->guard(fn (): int => $this->db($project)->execute($sql, $params));
    }

    /**
     * Toutes les lignes (export CSV), par lots.
     *
     * @param ProjectRow $project
     * @param TableDef   $table
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function export(array $project, array $table): \Generator
    {
        $columns = self::columns($table);
        $lastId = null;
        do {
            $rows = $lastId === null
                ? $this->db($project)->select('SELECT * FROM ' . Identifier::quote($table['name']) . ' ORDER BY `id` LIMIT 1000')
                : $this->db($project)->select('SELECT * FROM ' . Identifier::quote($table['name']) . ' WHERE `id` > ? ORDER BY `id` LIMIT 1000', [$lastId]);
            foreach ($rows as $row) {
                $lastId = $row['id'];
                yield $this->output($columns, $row);
            }
        } while (count($rows) === 1000);
    }

    /**
     * @param ProjectRow $project
     */
    private function db(array $project): Database
    {
        return $this->connections[$project['ref']] ??= $this->provisioner->connect($project);
    }

    /**
     * @param array<string, array{type: string, length: int|null, nullable: bool, writable: bool}> $columns
     * @param array<string, mixed>                                                               $row
     *
     * @return array<string, mixed>
     */
    private function writable(array $columns, array $row, bool $partial, ?int $index): array
    {
        $values = [];
        $errors = [];
        $prefix = $index !== null ? '[' . $index . '].' : '';
        foreach ($row as $name => $value) {
            if (!is_string($name) || !isset($columns[$name])) {
                $errors[$prefix . $name] = 'colonne inconnue';
                continue;
            }
            if (!$columns[$name]['writable']) {
                // Protection contre l'assignation de masse : colonnes système ignorées en silence.
                continue;
            }
            [$cast, $error] = ColumnTypes::cast($columns[$name], $value);
            if ($error !== null) {
                $errors[$prefix . $name] = $error;
                continue;
            }
            $values[$name] = $cast;
        }
        if (!$partial) {
            foreach ($columns as $name => $column) {
                if ($column['writable'] && !$column['nullable'] && !array_key_exists($name, $values) && !isset($errors[$prefix . $name])) {
                    $errors[$prefix . $name] = 'valeur obligatoire';
                }
            }
        }
        if ($errors !== []) {
            throw DataException::invalid($errors);
        }

        return $values;
    }

    /**
     * @param array<string, array{type: string, length: int|null, nullable: bool, writable: bool}> $columns
     */
    private function selection(array $columns, mixed $select): string
    {
        if (!is_string($select) || trim($select) === '' || trim($select) === '*') {
            return '*';
        }
        $names = [];
        foreach (explode(',', $select) as $name) {
            $name = trim($name);
            if (!isset($columns[$name])) {
                throw new HttpException(400, 'unknown_column', 'Colonne inconnue dans select : ' . mb_substr($name, 0, 64));
            }
            $names[$name] = Identifier::quote($name);
        }

        return implode(', ', $names);
    }

    /**
     * Filtres « colonne=opérateur.valeur » (eq, neq, gt, gte, lt, lte, like, ilike, in, is).
     *
     * @param array<string, array{type: string, length: int|null, nullable: bool, writable: bool}> $columns
     * @param array<string, mixed>                                                               $query
     *
     * @return array{0: string, 1: list<mixed>}
     */
    private function filters(array $columns, array $query, ?int $ownerId): array
    {
        $clauses = [];
        $params = [];
        foreach ($query as $name => $raw) {
            if (in_array($name, [...self::PAGING_PARAMS, 'q'], true) || !is_string($raw)) {
                continue;
            }
            if (!isset($columns[$name])) {
                throw new HttpException(400, 'unknown_column', 'Colonne de filtre inconnue : ' . mb_substr((string) $name, 0, 64));
            }
            [$op, $value] = array_pad(explode('.', $raw, 2), 2, '');
            $col = Identifier::quote((string) $name);
            $type = ['type' => $columns[$name]['type'], 'length' => $columns[$name]['length'], 'nullable' => false];
            switch ($op) {
                case 'eq': case 'neq': case 'gt': case 'gte': case 'lt': case 'lte':
                    [$cast, $error] = ColumnTypes::cast($type, $value);
                    if ($error !== null) {
                        throw new HttpException(400, 'invalid_filter', 'Filtre « ' . $name . ' » : ' . $error . '.');
                    }
                    $clauses[] = $col . ' ' . self::OPERATORS[$op] . ' ?';
                    $params[] = $cast;
                    break;
                case 'like':
                    $clauses[] = $col . ' LIKE ?';
                    $params[] = str_replace('*', '%', $value);
                    break;
                case 'ilike':
                    $clauses[] = 'LOWER(' . $col . ') LIKE LOWER(?)';
                    $params[] = str_replace('*', '%', $value);
                    break;
                case 'in':
                    $items = array_values(array_filter(array_map('trim', explode(',', trim($value, '()'))), static fn (string $v): bool => $v !== ''));
                    if ($items === [] || count($items) > 100) {
                        throw new HttpException(400, 'invalid_filter', 'Filtre « in » : entre 1 et 100 valeurs.');
                    }
                    $casted = [];
                    foreach ($items as $item) {
                        [$cast, $error] = ColumnTypes::cast($type, $item);
                        if ($error !== null) {
                            throw new HttpException(400, 'invalid_filter', 'Filtre « ' . $name . ' » : ' . $error . '.');
                        }
                        $casted[] = $cast;
                    }
                    $clauses[] = $col . ' IN (' . Database::placeholders($casted) . ')';
                    array_push($params, ...$casted);
                    break;
                case 'is':
                    $clauses[] = $col . match (strtolower($value)) {
                        'null' => ' IS NULL',
                        'notnull', 'not.null' => ' IS NOT NULL',
                        'true' => ' = 1',
                        'false' => ' = 0',
                        default => throw new HttpException(400, 'invalid_filter', 'Filtre « is » : null, notnull, true ou false.'),
                    };
                    break;
                default:
                    throw new HttpException(400, 'invalid_operator', 'Opérateur inconnu « ' . mb_substr($op, 0, 20) . ' » (eq, neq, gt, gte, lt, lte, like, ilike, in, is).');
            }
        }
        $search = $query['q'] ?? null;
        if (is_string($search) && trim($search) !== '') {
            $text = array_keys(array_filter($columns, static fn (array $c): bool => in_array($c['type'], ['string', 'text', 'uuid'], true)));
            if ($text !== []) {
                $clauses[] = '(' . implode(' OR ', array_map(static fn (string $c): string => Identifier::quote($c) . ' LIKE ?', $text)) . ')';
                array_push($params, ...array_fill(0, count($text), '%' . trim($search) . '%'));
            }
        }
        if ($ownerId !== null) {
            $clauses[] = '`owner_id` = ?';
            $params[] = $ownerId;
        }

        return [$clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses), $params];
    }

    /**
     * @param array<string, array{type: string, length: int|null, nullable: bool, writable: bool}> $columns
     */
    private function order(array $columns, mixed $order): string
    {
        if (!is_string($order) || trim($order) === '') {
            return ' ORDER BY `id` DESC';
        }
        $parts = [];
        foreach (array_slice(explode(',', $order), 0, 3) as $item) {
            [$name, $dir] = array_pad(explode('.', trim($item), 2), 2, 'asc');
            if (!isset($columns[$name])) {
                throw new HttpException(400, 'unknown_column', 'Colonne de tri inconnue : ' . mb_substr($name, 0, 64));
            }
            $parts[] = Identifier::quote($name) . (strtolower($dir) === 'desc' ? ' DESC' : ' ASC');
        }

        return ' ORDER BY ' . implode(', ', $parts);
    }

    /**
     * Conversion de sortie : booléens, JSON, nombres.
     *
     * @param array<string, array{type: string, length: int|null, nullable: bool, writable: bool}> $columns
     * @param array<string, mixed>                                                               $row
     *
     * @return array<string, mixed>
     */
    private function output(array $columns, array $row): array
    {
        foreach ($row as $name => $value) {
            if ($value === null || !isset($columns[$name])) {
                continue;
            }
            $row[$name] = match ($columns[$name]['type']) {
                'boolean' => (bool) $value,
                'json' => is_string($value) ? json_decode($value, true) : $value,
                'float' => (float) $value,
                'integer', 'bigint' => is_numeric($value) ? (int) $value : $value,
                'foreign' => is_numeric($value) ? (int) $value : $value,
                default => $value,
            };
        }

        return $row;
    }

    /**
     * Traduit les violations de contraintes MySQL en erreurs HTTP propres.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    private function guard(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (\PDOException $e) {
            $code = is_array($e->errorInfo) && isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;

            throw match ($code) {
                1062 => new HttpException(409, 'conflict', 'Une valeur unique existe déjà.'),
                1451 => new HttpException(409, 'conflict', 'Des lignes d\'autres tables dépendent de celle-ci.'),
                1452 => new HttpException(422, 'foreign_key_violation', 'La ligne liée (clé étrangère) n\'existe pas.'),
                1048, 1364 => new HttpException(422, 'validation_failed', 'Une colonne obligatoire est vide.'),
                1406 => new HttpException(422, 'validation_failed', 'Valeur trop longue.'),
                1264, 1265, 1292, 1366 => new HttpException(422, 'validation_failed', 'Valeur refusée par MySQL pour une colonne (hors limites ou non autorisée).'),
                default => $e,
            };
        }
    }

    private static function intParam(mixed $value, int $default, int $min, int $max): int
    {
        if (!is_string($value) && !is_int($value)) {
            return $default;
        }
        if (!is_int($value) && preg_match('/^\d{1,9}$/', $value) !== 1) {
            throw new HttpException(400, 'bad_request', 'limit et offset doivent être des entiers positifs.');
        }

        return max($min, min($max, (int) $value));
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
