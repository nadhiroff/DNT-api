<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Config;
use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Core\Session\Session;
use App\Repositories\PlanRepository;
use App\Repositories\UserRepository;
use App\Services\CommunityService;

/**
 * Pages publiques : accueil et pages légales.
 */
final class PageController
{
    private const array LEGAL = [
        'mentions-legales' => 'legal/mentions',
        'confidentialite' => 'legal/privacy',
        'conditions' => 'legal/terms',
    ];

    public function __construct(
        private readonly Responder $responder,
        private readonly PlanRepository $plans,
        private readonly UserRepository $users,
        private readonly Session $session,
        private readonly Config $config,
        private readonly CommunityService $community,
    ) {
    }

    public function home(Request $request): Response
    {
        $this->attachUser($request);

        $user = $request->attribute('user');
        $isSuperadmin = is_array($user) && ($user['role'] ?? null) === 'superadmin';

        return $this->responder->view($request, 'landing/home', [
            'plans' => $this->plans->publicPlans(),
            'community' => $this->community->settings(),
            'testimonials' => $this->community->forHomepage($isSuperadmin),
        ]);
    }

    public function legal(Request $request): Response
    {
        $page = self::LEGAL[$request->path() === '/' ? '' : ltrim($request->path(), '/')] ?? null;
        if ($page === null) {
            throw HttpException::notFound();
        }
        $this->attachUser($request);

        return $this->responder->view($request, $page, [
            'legal' => [
                'company' => $this->config->string('app.legal.company', 'NovaBase'),
                'address' => $this->config->string('app.legal.address'),
                'email' => $this->config->string('app.legal.email'),
                'host' => $this->config->string('app.legal.host'),
                'updated' => '24 octobre 2026',
            ],
        ]);
    }

    /**
     * Les pages publiques adaptent leur en-tête si le visiteur est connecté.
     */
    private function attachUser(Request $request): void
    {
        $userId = $this->session->userId();
        $user = $userId !== null ? $this->users->findById($userId) : null;
        if ($user !== null && $user['status'] === 'active') {
            $request->setAttribute('user', $user);
        }
    }
}
