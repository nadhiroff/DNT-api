<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database\Identifier;
use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Repositories\TableRepository;
use App\Services\Data\AccessPolicy;
use App\Services\Data\ProjectDataService;
use App\Services\UsageService;

/**
 * API REST des données : /api/v1/{ref}/data/{table}[/{id}].
 *
 * @phpstan-import-type ProjectRow from \App\Repositories\ProjectRepository
 * @phpstan-import-type TableDef from TableRepository
 */
final class DataApiController
{
    private const int MAX_BODY = 1_048_576;

    public function __construct(
        private readonly TableRepository $tables,
        private readonly ProjectDataService $data,
        private readonly UsageService $usage,
    ) {
    }

    public function index(Request $request): Response
    {
        [$project, $table, $keyType] = $this->context($request);
        $owner = AccessPolicy::check($table['access_rules'], 'read', $keyType, null);
        $result = $this->data->select($project, $table, $request->allQuery(), $owner);
        $end = $result['total'] === 0 ? 0 : min($result['offset'] + count($result['rows']), $result['total']) - 1;

        return Response::json($result['rows'], 200, [
            'X-Total-Count' => (string) $result['total'],
            'Content-Range' => ($result['rows'] === [] ? '*' : $result['offset'] . '-' . $end) . '/' . $result['total'],
        ]);
    }

    public function show(Request $request): Response
    {
        [$project, $table, $keyType] = $this->context($request);
        $owner = AccessPolicy::check($table['access_rules'], 'read', $keyType, null);
        $row = $this->data->find($project, $table, (string) $request->route('id'), $owner);
        if ($row === null) {
            throw new HttpException(404, 'not_found', 'Ligne introuvable.');
        }

        return Response::json($row);
    }

    public function store(Request $request): Response
    {
        [$project, $table, $keyType] = $this->context($request);
        $owner = AccessPolicy::check($table['access_rules'], 'insert', $keyType, null);
        $this->usage->assertCanWrite($project);
        $body = $request->json(self::MAX_BODY);
        $single = !array_is_list($body);
        $rows = $single ? [$body] : $body;
        foreach ($rows as $row) {
            if (!is_array($row) || array_is_list($row) && $row !== []) {
                throw new HttpException(400, 'bad_request', 'Envoyez un objet JSON ou un tableau d\'objets.');
            }
        }
        /** @var list<array<string, mixed>> $rows */
        $created = $this->data->insert($project, $table, $rows, $owner);

        return Response::json($single ? $created[0] : $created, 201);
    }

    public function update(Request $request): Response
    {
        [$project, $table, $keyType] = $this->context($request);
        $owner = AccessPolicy::check($table['access_rules'], 'update', $keyType, null);
        $this->usage->assertCanWrite($project);
        $body = $request->json(self::MAX_BODY);
        if ($body === [] || array_is_list($body)) {
            throw new HttpException(400, 'bad_request', 'Envoyez un objet JSON avec les colonnes à modifier.');
        }
        /** @var array<string, mixed> $body */
        $row = $this->data->update($project, $table, (string) $request->route('id'), $body, $request->isMethod('PATCH'), $owner);
        if ($row === null) {
            throw new HttpException(404, 'not_found', 'Ligne introuvable.');
        }

        return Response::json($row);
    }

    public function destroy(Request $request): Response
    {
        [$project, $table, $keyType] = $this->context($request);
        $owner = AccessPolicy::check($table['access_rules'], 'delete', $keyType, null);
        if (!$this->data->delete($project, $table, (string) $request->route('id'), $owner)) {
            throw new HttpException(404, 'not_found', 'Ligne introuvable.');
        }

        return new Response('', 204);
    }

    /**
     * Suppression groupée : DELETE /data/{table}?<filtres>. Au moins un filtre est obligatoire
     * (garde-fou contre un effacement accidentel) ; « id=gt.0 » supprime tout.
     */
    public function destroyMany(Request $request): Response
    {
        [$project, $table, $keyType] = $this->context($request);
        $owner = AccessPolicy::check($table['access_rules'], 'delete', $keyType, null);
        $filters = array_diff_key($request->allQuery(), array_flip(ProjectDataService::PAGING_PARAMS));
        if ($filters === []) {
            throw new HttpException(400, 'filter_required', 'Ajoutez au moins un filtre (par exemple ?id=in.(1,2,3) ou ?id=gt.0 pour tout supprimer).');
        }
        $deleted = $this->data->deleteWhere($project, $table, $filters, $owner);

        return Response::json(['deleted' => $deleted]);
    }

    /**
     * @return array{0: ProjectRow, 1: TableDef, 2: string}
     */
    private function context(Request $request): array
    {
        /** @var ProjectRow $project */
        $project = $request->attribute('project');
        /** @var array{type: string} $key */
        $key = $request->attribute('api_key');
        $name = (string) $request->route('table');
        $table = Identifier::problem($name) === null ? $this->tables->find($project['id'], $name) : null;
        if ($table === null) {
            throw new HttpException(404, 'table_not_found', 'Table « ' . mb_substr($name, 0, 64) . ' » introuvable dans ce projet.');
        }

        return [$project, $table, $key['type']];
    }
}
