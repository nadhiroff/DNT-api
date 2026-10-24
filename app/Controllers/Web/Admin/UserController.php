<?php

declare(strict_types=1);

namespace App\Controllers\Web\Admin;

use App\Controllers\Web\CurrentUser;
use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Repositories\PlanRepository;
use App\Repositories\SessionRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;

/**
 * Utilisateurs de la plateforme : recherche, offre, suspension.
 */
final class UserController
{
    private const int PER_PAGE = 25;

    public function __construct(
        private readonly Responder $responder,
        private readonly UserRepository $users,
        private readonly PlanRepository $plans,
        private readonly SessionRepository $sessions,
        private readonly AuditService $audit,
    ) {
    }

    public function index(Request $request): Response
    {
        $search = mb_substr(trim((string) (is_string($request->query('q')) ? $request->query('q') : '')), 0, 100);
        $status = is_string($request->query('status')) && in_array($request->query('status'), ['active', 'pending', 'suspended'], true) ? (string) $request->query('status') : '';
        $page = max(1, (int) (is_string($request->query('page')) ? $request->query('page') : 1));
        $total = $this->users->countForAdmin($search, $status);

        return $this->responder->view($request, 'admin/users', [
            'users' => $this->users->listForAdmin($search, $status, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'plans' => $this->plans->options(),
            'search' => $search,
            'status' => $status,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / self::PER_PAGE)),
            'total' => $total,
        ]);
    }

    public function updateStatus(Request $request): Response
    {
        $actor = CurrentUser::from($request);
        $target = $this->target($request, $actor['id']);
        $status = $request->string('status');
        if (!in_array($status, ['active', 'suspended'], true) || $target['status'] === 'pending') {
            throw HttpException::badRequest();
        }
        $this->users->setStatus($target['id'], $status);
        if ($status === 'suspended') {
            // Déconnexion immédiate sur tous les appareils.
            $this->sessions->revokeAllExcept($target['id'], null);
        }
        $this->audit->record($status === 'suspended' ? 'admin.user_suspended' : 'admin.user_reactivated', $actor['id'], 'user:' . $target['id'], $request->ip());

        return $this->responder->flash('success', $status === 'suspended' ? 'Compte suspendu et déconnecté.' : 'Compte réactivé.')->redirect('/admin/users');
    }

    public function updatePlan(Request $request): Response
    {
        $actor = CurrentUser::from($request);
        $target = $this->target($request, $actor['id']);
        $planId = (int) $request->string('plan_id');
        if (!in_array($planId, array_column($this->plans->options(), 'id'), true)) {
            throw HttpException::badRequest('Offre inconnue.');
        }
        $this->users->setPlan($target['id'], $planId);
        $this->audit->record('admin.user_plan_changed', $actor['id'], 'user:' . $target['id'], $request->ip(), ['plan_id' => $planId]);

        return $this->responder->flash('success', 'Offre mise à jour.')->redirect('/admin/users');
    }

    /**
     * @return array{id: int, role: string, status: string}
     */
    private function target(Request $request, int $actorId): array
    {
        $target = $this->users->findById((int) $request->route('id'));
        if ($target === null) {
            throw HttpException::notFound();
        }
        if ($target['id'] === $actorId || $target['role'] === 'superadmin') {
            throw HttpException::forbidden('Action impossible sur un compte superadmin.');
        }

        return $target;
    }
}
