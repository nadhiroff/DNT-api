<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\UrlGenerator;
use App\Repositories\TableRepository;
use App\Services\Data\OpenApiGenerator;
use App\Services\Projects\ProjectProvisioner;

/**
 * Point d'entrée de l'API d'un projet : vérifie la clé et décrit le projet.
 */
final class ProjectApiController
{
    public function __construct(
        private readonly ProjectProvisioner $provisioner,
        private readonly TableRepository $tables,
        private readonly UrlGenerator $urls,
    ) {
    }

    public function openapi(Request $request): Response
    {
        /** @var array{id: int, ref: string, name: string} $project */
        $project = $request->attribute('project');

        return Response::json(OpenApiGenerator::generate($project, $this->tables->forProject($project['id']), $this->urls->absolute('/api/v1/' . $project['ref'])));
    }

    public function info(Request $request): Response
    {
        /** @var array{id: int, ref: string, owner_id: int, name: string, db_name: string, db_user: string, db_pass_enc: string, jwt_secret_enc: string, status: string, created_at: string} $project */
        $project = $request->attribute('project');
        /** @var array{type: string, permissions: list<string>} $key */
        $key = $request->attribute('api_key');

        // Lecture avec l'utilisateur MySQL du projet : jamais avec le compte d'administration.
        $tables = array_map(static fn (array $row): string => (string) array_values($row)[0], $this->provisioner->connect($project)->select('SHOW TABLES'));

        return Response::json([
            'project' => ['ref' => $project['ref'], 'name' => $project['name'], 'status' => $project['status']],
            'key' => ['type' => $key['type'], 'permissions' => $key['permissions']],
            'tables' => $tables,
        ]);
    }
}
