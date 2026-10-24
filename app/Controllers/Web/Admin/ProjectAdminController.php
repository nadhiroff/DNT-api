<?php

declare(strict_types=1);

namespace App\Controllers\Web\Admin;

use App\Controllers\Web\CurrentUser;
use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Repositories\ProjectDatabaseInspector;
use App\Repositories\ProjectRepository;
use App\Services\Projects\ProjectService;

/**
 * Tous les projets de la plateforme (superadmin) : taille, suspension.
 */
final class ProjectAdminController
{
    public function __construct(
        private readonly Responder $responder,
        private readonly ProjectRepository $projects,
        private readonly ProjectService $service,
        private readonly ProjectDatabaseInspector $inspector,
    ) {
    }

    public function index(Request $request): Response
    {
        $search = mb_substr(trim(is_string($request->query('q')) ? (string) $request->query('q') : ''), 0, 100);
        $rows = $this->projects->allWithOwner($search, 100, 0);

        return $this->responder->view($request, 'admin/projects', [
            'projects' => $rows,
            'sizes' => $this->inspector->sizes(array_column($rows, 'db_name')),
            'search' => $search,
        ]);
    }

    public function toggleSuspend(Request $request): Response
    {
        $project = $this->projects->findByRef((string) $request->route('ref'));
        if ($project === null || !in_array($project['status'], ['active', 'suspended'], true)) {
            throw HttpException::notFound();
        }
        $suspend = $project['status'] === 'active';
        $this->service->setSuspended($project, $suspend, CurrentUser::from($request)['id'], $request->ip());

        return $this->responder->flash('success', $suspend ? 'Projet suspendu : son API répond désormais 403.' : 'Projet réactivé.')->redirect('/admin/projects');
    }
}
