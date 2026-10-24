<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Core\Validation\ValidationException;
use App\Core\Validation\Validator;
use App\Repositories\TableRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\Data\DataException;
use App\Services\Data\DynamicTableService;
use App\Services\Data\ProjectDataService;
use App\Services\Projects\ProjectService;
use App\Services\UsageService;

/**
 * Table Editor : tables, structure, règles d'accès, grille de données, import / export CSV.
 * La console agit en « mode service » (comme la clé secrète) : les règles d'accès ne s'appliquent pas.
 *
 * @phpstan-import-type ProjectRow from \App\Repositories\ProjectRepository
 * @phpstan-import-type TableDef from TableRepository
 */
final class TableController
{
    private const int PAGE_SIZE = 50;

    private const int CSV_MAX_BYTES = 5_242_880;

    public function __construct(
        private readonly Responder $responder,
        private readonly ProjectService $projects,
        private readonly DynamicTableService $schema,
        private readonly ProjectDataService $data,
        private readonly UsageService $usage,
        private readonly UserRepository $users,
        private readonly TableRepository $tableRepository,
        private readonly AuditService $audit,
        private readonly \App\Core\Config $config,
        private readonly \App\Core\Session\Session $session,
    ) {
    }

    public function index(Request $request): Response
    {
        $project = $this->project($request);
        $tables = $this->schema->tables($project);
        $sizes = [];
        foreach ($this->projects->database($project)['tables'] as $t) {
            $sizes[$t['name']] = $t;
        }

        return $this->responder->view($request, 'projects/tables', [
            'project' => $project,
            'tab' => 'tables',
            'tables' => $tables,
            'stats' => $sizes,
            'changes' => $this->tableRepository->changes($project['id'], 10),
            'dbHost' => $this->config->string('database.platform.host', '127.0.0.1'),
            'dbPort' => $this->config->int('database.platform.port', 3306),
            'revealedPassword' => $this->session->getFlash('_revealed_db_password'),
            'database' => $this->projects->database($project),
        ]);
    }

    public function create(Request $request): Response
    {
        $project = $this->project($request);

        return $this->responder->view($request, 'projects/table-new', [
            'project' => $project,
            'tab' => 'tables',
            'tables' => $this->schema->tables($project),
        ]);
    }

    public function store(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $project = $this->project($request);
        $existing = $this->schema->tables($project);
        $columns = [];
        $raw = $request->input('columns', []);
        foreach (is_array($raw) ? $raw : [] as $column) {
            if (is_array($column) && trim(is_string($column['name'] ?? null) ? $column['name'] : '') !== '') {
                /** @var array<string, mixed> $column */
                $columns[] = $this->schema->normalizeColumn($column, $existing);
            }
        }
        $name = $request->string('name');
        $owner = $this->owner($project);
        $this->schema->createTable($project, $owner, $user['id'], $name, $request->string('pk_type'), $columns);
        $this->audit->record('table.created', $user['id'], 'project:' . $project['ref'], $request->ip(), ['table' => $name]);

        return $this->responder->flash('success', 'Table « ' . strtolower($name) . ' » créée. Son API est disponible immédiatement.')->redirect($this->tablePath($project, strtolower($name)));
    }

    public function show(Request $request): Response
    {
        $project = $this->project($request);
        $table = $this->table($project, $request);
        $view = is_string($request->query('view')) && in_array($request->query('view'), ['data', 'structure', 'rules'], true) ? (string) $request->query('view') : 'data';
        $page = max(1, (int) (is_string($request->query('page')) ? $request->query('page') : 1));
        $order = is_string($request->query('order')) ? (string) $request->query('order') : '';
        $search = is_string($request->query('q')) ? mb_substr(trim((string) $request->query('q')), 0, 100) : '';

        $result = ['rows' => [], 'total' => 0, 'limit' => self::PAGE_SIZE, 'offset' => 0];
        $error = null;
        if ($view === 'data') {
            try {
                $result = $this->data->select($project, $table, ['order' => $order, 'limit' => (string) self::PAGE_SIZE, 'offset' => (string) (($page - 1) * self::PAGE_SIZE), 'q' => $search]);
            } catch (HttpException $e) {
                $error = $e->getMessage();
            }
        }

        return $this->responder->view($request, 'projects/table', [
            'project' => $project,
            'tab' => 'tables',
            'table' => $table,
            'tables' => $this->schema->tables($project),
            'view' => $view,
            'columns' => ProjectDataService::columns($table),
            'result' => $result,
            'page' => $page,
            'pages' => max(1, (int) ceil($result['total'] / self::PAGE_SIZE)),
            'order' => $order,
            'search' => $search,
            'gridError' => $error,
        ]);
    }

    public function addColumn(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $project = $this->project($request);
        $table = $this->table($project, $request);
        $raw = $request->input('column', []);
        /** @var array<string, mixed> $raw */
        $raw = is_array($raw) ? $raw : [];
        $column = $this->schema->normalizeColumn($raw, $this->schema->tables($project), 'column');
        $this->schema->addColumn($project, $user['id'], $table['name'], $column);

        return $this->responder->flash('success', 'Colonne « ' . $column['name'] . ' » ajoutée.')->redirect($this->tablePath($project, $table['name'], 'structure'));
    }

    public function dropColumn(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $project = $this->project($request);
        $table = $this->table($project, $request);
        $this->schema->dropColumn($project, $user['id'], $table['name'], $request->string('column_name'));

        return $this->responder->flash('success', 'Colonne supprimée.')->redirect($this->tablePath($project, $table['name'], 'structure'));
    }

    public function rename(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $project = $this->project($request);
        $table = $this->table($project, $request);
        $new = strtolower($request->string('new_name'));
        $this->schema->renameTable($project, $user['id'], $table['name'], $new);

        return $this->responder->flash('success', 'Table renommée en « ' . $new . ' ». Pensez à mettre à jour l\'URL dans vos applications.')->redirect($this->tablePath($project, $new, 'structure'));
    }

    public function destroy(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $project = $this->project($request);
        $table = $this->table($project, $request);
        if (!hash_equals($table['name'], $request->string('confirm_table'))) {
            throw ValidationException::withMessage('confirm_table', 'Saisissez exactement le nom de la table pour confirmer.');
        }
        $this->schema->dropTable($project, $user['id'], $table['name']);
        $this->audit->record('table.deleted', $user['id'], 'project:' . $project['ref'], $request->ip(), ['table' => $table['name']]);

        return $this->responder->flash('success', 'Table « ' . $table['name'] . ' » supprimée.')->redirect('/projects/' . $project['ref'] . '/tables');
    }

    public function updateRules(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $project = $this->project($request);
        $table = $this->table($project, $request);
        $rules = [];
        foreach (array_keys(TableRepository::DEFAULT_RULES) as $op) {
            $rules[$op] = $request->string('rule_' . $op);
        }
        /** @var array{read: string, insert: string, update: string, delete: string} $rules */
        $this->schema->updateRules($project, $user['id'], $table['name'], $rules);

        return $this->responder->flash('success', 'Règles d\'accès enregistrées.')->redirect($this->tablePath($project, $table['name'], 'rules'));
    }

    public function newRow(Request $request): Response
    {
        $project = $this->project($request);
        $table = $this->table($project, $request);

        return $this->responder->view($request, 'projects/row-form', ['project' => $project, 'tab' => 'tables', 'table' => $table, 'row' => null]);
    }

    public function storeRow(Request $request): Response
    {
        $project = $this->project($request);
        $table = $this->table($project, $request);
        $this->usage->assertCanWrite($project);
        $values = $this->formValues($table, $request);
        try {
            $this->data->insert($project, $table, [$values]);
        } catch (DataException $e) {
            throw $this->formError($e, $values);
        } catch (HttpException $e) {
            throw ValidationException::withMessage('_row', $e->getMessage(), array_filter($values, 'is_scalar'));
        }

        return $this->responder->flash('success', 'Ligne ajoutée.')->redirect($this->tablePath($project, $table['name']));
    }

    public function editRow(Request $request): Response
    {
        $project = $this->project($request);
        $table = $this->table($project, $request);
        $row = $this->data->find($project, $table, (string) $request->route('id'));
        if ($row === null) {
            throw HttpException::notFound('Ligne introuvable.');
        }

        return $this->responder->view($request, 'projects/row-form', ['project' => $project, 'tab' => 'tables', 'table' => $table, 'row' => $row]);
    }

    public function updateRow(Request $request): Response
    {
        $project = $this->project($request);
        $table = $this->table($project, $request);
        $this->usage->assertCanWrite($project);
        $values = $this->formValues($table, $request);
        try {
            $row = $this->data->update($project, $table, (string) $request->route('id'), $values, true);
        } catch (DataException $e) {
            throw $this->formError($e, $values);
        } catch (HttpException $e) {
            throw ValidationException::withMessage('_row', $e->getMessage(), array_filter($values, 'is_scalar'));
        }
        if ($row === null) {
            throw HttpException::notFound('Ligne introuvable.');
        }

        return $this->responder->flash('success', 'Ligne mise à jour.')->redirect($this->tablePath($project, $table['name']));
    }

    public function deleteRow(Request $request): Response
    {
        $project = $this->project($request);
        $table = $this->table($project, $request);
        try {
            $deleted = $this->data->delete($project, $table, (string) $request->route('id'));
        } catch (HttpException $e) {
            return $this->responder->flash('error', $e->getMessage())->redirect($this->tablePath($project, $table['name']));
        }

        return $this->responder->flash($deleted ? 'success' : 'error', $deleted ? 'Ligne supprimée.' : 'Ligne introuvable.')->redirect($this->tablePath($project, $table['name']));
    }

    public function export(Request $request): Response
    {
        $project = $this->project($request);
        $table = $this->table($project, $request);
        $columns = array_keys(ProjectDataService::columns($table));
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            throw new \RuntimeException('Export impossible.');
        }
        fwrite($out, "\xEF\xBB\xBF"); // BOM : ouverture correcte des accents dans Excel.
        fputcsv($out, $columns, ';', '"', '');
        foreach ($this->data->export($project, $table) as $row) {
            fputcsv($out, array_map(static fn (string $c): string => self::csvCell($row[$c] ?? null), $columns), ';', '"', '');
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $table['name'] . '-' . gmdate('Ymd-His') . '.csv"',
        ]);
    }

    public function import(Request $request): Response
    {
        $project = $this->project($request);
        $table = $this->table($project, $request);
        $this->usage->assertCanWrite($project);
        $file = $request->files()['csv'] ?? null;
        $tmp = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_string($file['tmp_name'] ?? null) ? $file['tmp_name'] : null;
        if ($tmp === null || (PHP_SAPI !== 'cli' && !is_uploaded_file($tmp)) || filesize($tmp) > self::CSV_MAX_BYTES) {
            return $this->responder->flash('error', 'Fichier CSV manquant ou trop lourd (5 Mo maximum).')->redirect($this->tablePath($project, $table['name']));
        }
        $rows = $this->readCsv($tmp, $table);
        if ($rows === []) {
            return $this->responder->flash('error', 'Aucune ligne exploitable : la première ligne doit contenir les noms des colonnes.')->redirect($this->tablePath($project, $table['name']));
        }
        $imported = 0;
        try {
            foreach (array_chunk($rows, ProjectDataService::MAX_INSERT) as $chunk) {
                $imported += count($this->data->insert($project, $table, $chunk));
            }
        } catch (DataException $e) {
            $fields = $e->details()['fields'] ?? [];
            $first = is_array($fields) ? array_key_first($fields) : null;

            return $this->responder->flash('error', 'Import interrompu après ' . $imported . ' ligne(s) : ' . ($first !== null ? 'ligne ' . $first . ' ' . (is_string($fields[$first]) ? $fields[$first] : '') : $e->getMessage()))->redirect($this->tablePath($project, $table['name']));
        } catch (HttpException $e) {
            return $this->responder->flash('error', 'Import interrompu après ' . $imported . ' ligne(s) : ' . $e->getMessage())->redirect($this->tablePath($project, $table['name']));
        }

        return $this->responder->flash('success', $imported . ' ligne(s) importée(s).')->redirect($this->tablePath($project, $table['name']));
    }

    /**
     * @param TableDef $table
     *
     * @return list<array<string, mixed>>
     */
    private function readCsv(string $path, array $table): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }
        $first = (string) fgets($handle);
        $delimiter = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
        $header = array_map(static fn (?string $h): string => strtolower(trim((string) $h, " \t\"\xEF\xBB\xBF")), str_getcsv($first, $delimiter, '"', ''));
        $writable = array_keys(array_filter(ProjectDataService::columns($table), static fn (array $c): bool => $c['writable']));
        $rows = [];
        while (($line = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            if ($line === [null]) {
                continue;
            }
            $row = [];
            foreach ($header as $i => $name) {
                if (in_array($name, $writable, true)) {
                    $row[$name] = isset($line[$i]) && $line[$i] !== '' ? $line[$i] : null;
                }
            }
            if ($row !== []) {
                $rows[] = $row;
            }
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @param TableDef $table
     *
     * @return array<string, mixed>
     */
    private function formValues(array $table, Request $request): array
    {
        $values = [];
        foreach ($table['columns'] as $column) {
            $raw = $request->input('f_' . $column['name']);
            if ($column['type'] === 'boolean') {
                $values[$column['name']] = $raw !== null && $raw !== '0' && $raw !== '' ? 1 : 0;
                continue;
            }
            $values[$column['name']] = is_string($raw) ? ($column['type'] === 'datetime' ? str_replace('T', ' ', $raw) . (strlen($raw) === 16 ? ':00' : '') : $raw) : null;
            if ($values[$column['name']] === '' && !in_array($column['type'], ['string', 'text'], true)) {
                $values[$column['name']] = null;
            }
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function formError(DataException $e, array $values): ValidationException
    {
        $errors = [];
        $fields = $e->details()['fields'] ?? [];
        foreach (is_array($fields) ? $fields : [] as $name => $message) {
            $errors['f_' . $name] = [ucfirst(is_string($message) ? $message : 'valeur invalide') . '.'];
        }
        $old = [];
        foreach ($values as $name => $value) {
            if (is_scalar($value)) {
                $old['f_' . $name] = $value;
            }
        }

        return new ValidationException($errors, $old);
    }

    private static function csvCell(mixed $value): string
    {
        $text = match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE) ?: '',
            is_scalar($value) => (string) $value,
            default => '',
        };

        // Neutralise l'injection de formules dans les tableurs (=, +, -, @).
        return preg_match('/^[=+\-@\t\r]/', $text) === 1 ? "'" . $text : $text;
    }

    /**
     * @param ProjectRow $project
     */
    private function tablePath(array $project, string $table, string $view = 'data'): string
    {
        return '/projects/' . $project['ref'] . '/tables/' . $table . ($view !== 'data' ? '?view=' . $view : '');
    }

    /**
     * @param ProjectRow $project
     *
     * @return array{id: int, role: string, plan_id: int|null}
     */
    private function owner(array $project): array
    {
        $owner = $this->users->findById($project['owner_id']);
        if ($owner === null) {
            throw HttpException::notFound();
        }

        return $owner;
    }

    /**
     * @return ProjectRow
     */
    private function project(Request $request): array
    {
        $project = $this->projects->findAccessible(CurrentUser::from($request), (string) $request->route('ref'));
        if ($project === null) {
            throw HttpException::notFound('Projet introuvable.');
        }

        return $project;
    }

    /**
     * @param ProjectRow $project
     *
     * @return TableDef
     */
    private function table(array $project, Request $request): array
    {
        $table = $this->schema->table($project, (string) $request->route('table'));
        if ($table === null) {
            throw HttpException::notFound('Table introuvable.');
        }

        return $table;
    }
}
