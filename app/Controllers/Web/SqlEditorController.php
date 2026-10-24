<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Repositories\TableRepository;
use App\Services\AuditService;
use App\Services\Data\DynamicTableService;
use App\Services\Data\SqlEditorService;
use App\Services\Projects\ProjectService;

/**
 * Éditeur SQL d'un projet : exécution directe de requêtes sur la base du projet.
 *
 * @phpstan-import-type ProjectRow from \App\Repositories\ProjectRepository
 */
final class SqlEditorController
{
    public function __construct(
        private readonly Responder $responder,
        private readonly ProjectService $projects,
        private readonly SqlEditorService $editor,
        private readonly DynamicTableService $schema,
        private readonly TableRepository $tables,
        private readonly AuditService $audit,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->render($request, $this->project($request), '', null);
    }

    public function run(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $project = $this->project($request);
        $input = $request->input('sql', '');
        $sql = is_string($input) ? $input : '';
        $outcome = $this->editor->execute($project, $user['id'], $sql);
        $this->audit->record('sql.executed', $user['id'], 'project:' . $project['ref'], $request->ip(), ['statements' => count($outcome['results'])]);

        return $this->render($request, $project, $sql, $outcome);
    }

    /**
     * @param ProjectRow $project
     * @param array<string, mixed>|null $outcome
     */
    private function render(Request $request, array $project, string $sql, ?array $outcome): Response
    {
        $tables = $this->schema->tables($project);

        return $this->responder->view($request, 'projects/sql', [
            'project' => $project,
            'tab' => 'sql',
            'sql' => $sql,
            'outcome' => $outcome,
            'tables' => $tables,
            'snippets' => SqlEditorService::SNIPPETS,
            'selectSnippets' => SqlEditorService::selectSnippets(array_column($tables, 'name')),
            'history' => $this->tables->sqlHistory($project['id']),
            'maxRows' => SqlEditorService::MAX_ROWS,
        ]);
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
}
