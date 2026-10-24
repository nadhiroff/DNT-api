<?php

declare(strict_types=1);

namespace App\Controllers\Web\Admin;

use App\Controllers\Web\CurrentUser;
use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Core\Config;
use App\Repositories\AdminStatsRepository;
use App\Repositories\ApiUsageRepository;
use App\Repositories\InvitationRepository;
use App\Repositories\LoginEventRepository;
use App\Repositories\ProjectDatabaseInspector;
use App\Repositories\ProjectRepository;
use App\Repositories\SiteSettingRepository;
use App\Services\AuditService;
use App\Services\CommunityService;
use App\Services\ServerMetrics;

/**
 * Espace superadmin : vue d'ensemble et réglages de la section communauté.
 */
final class AdminController
{
    public function __construct(
        private readonly Responder $responder,
        private readonly AdminStatsRepository $stats,
        private readonly CommunityService $community,
        private readonly SiteSettingRepository $settings,
        private readonly AuditService $audit,
        private readonly ProjectRepository $projects,
        private readonly ApiUsageRepository $usage,
        private readonly InvitationRepository $invitations,
        private readonly LoginEventRepository $loginEvents,
        private readonly ServerMetrics $metrics,
        private readonly ProjectDatabaseInspector $inspector,
        private readonly Config $config,
    ) {
    }

    public function dashboard(Request $request): Response
    {
        return $this->responder->view($request, 'admin/dashboard', [
            'stats' => $this->stats->overview(),
            'projects' => $this->projects->countAll(),
            'requests30' => $this->usage->total(null, 30),
            'requestsDaily' => $this->usage->daily(null, 30),
            'signupsDaily' => $this->stats->signupsDaily(30),
            'pendingInvitations' => $this->invitations->countPending(),
            'logins' => $this->loginEvents->summary(24),
            'server' => $this->metrics->snapshot(),
            'databases' => $this->inspector->overview($this->config->string('database.platform_database', 'novabase')),
        ]);
    }

    /**
     * Métriques serveur pour les jauges en temps réel (JSON, interrogé toutes les 5 s).
     */
    public function metrics(Request $request): Response
    {
        return Response::json($this->metrics->snapshot(), 200, ['Cache-Control' => 'no-store']);
    }

    public function showCommunity(Request $request): Response
    {
        return $this->responder->view($request, 'admin/community', ['settings' => $this->community->settings()]);
    }

    public function updateCommunity(Request $request): Response
    {
        $data = Validator::validate($request->all(), [
            'community_title' => 'required|string|max:120',
            'community_subtitle' => 'nullable|string|max:300',
            'community_button_label' => 'nullable|string|max:60',
            'community_button_url' => 'nullable|url|max:500',
        ], [
            'community_title' => 'titre',
            'community_subtitle' => 'sous-titre',
            'community_button_label' => 'texte du bouton',
            'community_button_url' => 'lien du bouton',
        ]);
        foreach (CommunityService::SETTINGS as $key) {
            $value = $data[$key] ?? null;
            $this->settings->set($key, is_string($value) ? $value : null);
        }
        $user = CurrentUser::from($request);
        $this->audit->record('admin.community_updated', $user['id'], 'site_settings', $request->ip());

        return $this->responder->flash('success', 'Section communauté mise à jour.')->redirect('/admin/community');
    }
}
