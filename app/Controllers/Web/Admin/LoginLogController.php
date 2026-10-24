<?php

declare(strict_types=1);

namespace App\Controllers\Web\Admin;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Repositories\LoginEventRepository;
use App\Services\GeoIpService;

/**
 * Journal des connexions en temps réel (superadmin).
 */
final class LoginLogController
{
    public const array EVENTS = [
        'login' => 'Connexion',
        'login_failed' => 'Échec',
        '2fa_failed' => 'Échec 2FA',
        'locked' => 'Bloqué',
        'logout' => 'Déconnexion',
        'invitation_accepted' => 'Invitation acceptée',
    ];

    private const int PER_PAGE = 50;

    public function __construct(
        private readonly Responder $responder,
        private readonly LoginEventRepository $events,
        private readonly GeoIpService $geo,
    ) {
    }

    public function index(Request $request): Response
    {
        [$event, $search] = $this->filters($request);
        $page = max(1, (int) (is_string($request->query('page')) ? $request->query('page') : 1));
        $rows = $this->events->latest($event, $search, 0, self::PER_PAGE + 1, ($page - 1) * self::PER_PAGE);
        $hasMore = count($rows) > self::PER_PAGE;
        $rows = array_slice($rows, 0, self::PER_PAGE);

        return $this->responder->view($request, 'admin/logins', [
            'rows' => $rows,
            'locations' => $this->geo->locate(array_column($rows, 'ip')),
            'summary' => $this->events->summary(24),
            'event' => $event,
            'search' => $search,
            'page' => $page,
            'hasMore' => $hasMore,
        ]);
    }

    /**
     * Nouveaux événements depuis $after (rafraîchissement en direct).
     */
    public function feed(Request $request): Response
    {
        [$event, $search] = $this->filters($request);
        $after = max(0, (int) (is_string($request->query('after')) ? $request->query('after') : 0));
        $rows = array_reverse($this->events->latest($event, $search, $after, 50, 0));
        $locations = $this->geo->locate(array_column($rows, 'ip'));

        return Response::json(['events' => array_map(static fn (array $r): array => [
            'id' => $r['id'],
            'user' => $r['user_name'] ?? '',
            'email' => $r['email'] ?? '',
            'event' => $r['event'],
            'event_label' => self::EVENTS[$r['event']] ?? $r['event'],
            'ip' => $r['ip'],
            'location' => $locations[$r['ip']] ?? 'Inconnue',
            'browser' => $r['browser'],
            'os' => $r['os'],
            'device' => $r['device'],
            'created_at' => $r['created_at'],
        ], $rows)], 200, ['Cache-Control' => 'no-store']);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function filters(Request $request): array
    {
        $event = is_string($request->query('event')) && isset(self::EVENTS[$request->query('event')]) ? (string) $request->query('event') : '';
        $search = mb_substr(trim(is_string($request->query('q')) ? (string) $request->query('q') : ''), 0, 100);

        return [$event, $search];
    }
}
