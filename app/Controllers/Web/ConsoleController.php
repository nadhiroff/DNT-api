<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Repositories\ApiUsageRepository;
use App\Services\Projects\ProjectService;
use App\Services\QuotaService;

/**
 * Tableau de bord de l'utilisateur : projets, requêtes, stockage et quotas.
 */
final class ConsoleController
{
    public function __construct(
        private readonly Responder $responder,
        private readonly ProjectService $projects,
        private readonly ApiUsageRepository $usage,
        private readonly QuotaService $quotas,
    ) {
    }

    public function dashboard(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $items = $this->projects->listForOwner($user);
        $daily = $this->usage->daily($user['id'], 30);
        $bytes = array_sum(array_column($items, 'bytes'));

        return $this->responder->view($request, 'console/dashboard', [
            'user' => $user,
            'items' => array_slice($items, 0, 6),
            'projectCount' => count($items),
            'daily' => $daily,
            'requests30' => array_sum($daily),
            'bytes' => $bytes,
            'quotas' => [
                'projects' => ['used' => count($items), 'limit' => $this->quotas->limit($user, 'projects')],
                'db_storage_bytes' => ['used' => $bytes, 'limit' => $this->quotas->limit($user, 'db_storage_bytes')],
                'api_requests_month' => ['used' => array_sum($daily), 'limit' => $this->quotas->limit($user, 'api_requests_month')],
            ],
        ]);
    }
}
