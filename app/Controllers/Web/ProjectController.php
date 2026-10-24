<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Config;
use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Core\Session\Session;
use App\Core\Validation\ValidationException;
use App\Core\Validation\Validator;
use App\Repositories\ApiUsageRepository;
use App\Services\AuditService;
use App\Services\Projects\ApiKeyService;
use App\Services\Projects\ProjectService;
use App\Services\Projects\ProvisioningException;
use App\Services\QuotaService;

/**
 * Projets de l'utilisateur : liste, création, vue d'ensemble, base de données, clés API, paramètres.
 *
 * @phpstan-import-type ProjectRow from \App\Repositories\ProjectRepository
 */
final class ProjectController
{
    private const string NEW_KEYS = '_new_api_keys';

    public function __construct(
        private readonly Responder $responder,
        private readonly ProjectService $projects,
        private readonly ApiKeyService $keys,
        private readonly QuotaService $quotas,
        private readonly ApiUsageRepository $usage,
        private readonly AuditService $audit,
        private readonly Session $session,
        private readonly Config $config,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $items = $this->projects->listForOwner($user);

        return $this->responder->view($request, 'projects/index', [
            'items' => $items,
            'limit' => $this->quotas->limit($user, 'projects'),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $limit = $this->quotas->limit($user, 'projects');

        return $this->responder->view($request, 'projects/create', [
            'limit' => $limit,
            'count' => count($this->projects->listForOwner($user)),
        ]);
    }

    public function store(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $data = Validator::validate($request->all(), [
            'name' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[\pL\pN][\pL\pN _\-\.\']*$/u'],
        ], ['name' => 'nom du projet']);
        try {
            $result = $this->projects->create($user, (string) $data['name'], $request->ip());
        } catch (ProvisioningException $e) {
            throw ValidationException::withMessage('name', $e->getMessage(), ['name' => $data['name']]);
        }
        // Les clés ne seront plus jamais affichées en clair : une seule lecture, via la session.
        $this->session->flash(self::NEW_KEYS, $result['keys']);

        return $this->responder->flash('success', 'Projet « ' . $result['project']['name'] . ' » créé : sa base de données est prête.')
            ->redirect('/projects/' . $result['project']['ref'] . '/keys');
    }

    public function show(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $project = $this->project($request);
        $database = $this->projects->database($project);
        $daily = $this->projectDaily($project);

        return $this->responder->view($request, 'projects/show', [
            'project' => $project,
            'tab' => 'overview',
            'database' => $database,
            'daily' => $daily,
            'requests30' => array_sum($daily),
            'keyCount' => count(array_filter($this->keys->forProject($project['id']), static fn (array $k): bool => $k['revoked_at'] === null)),
            'apiUrl' => $this->apiUrl($project),
            'isOwner' => $project['owner_id'] === $user['id'],
        ]);
    }

    public function database(Request $request): Response
    {
        $project = $this->project($request);

        return $this->responder->view($request, 'projects/show', [
            'project' => $project,
            'tab' => 'database',
            'database' => $this->projects->database($project),
            'dbHost' => $this->config->string('database.platform.host', '127.0.0.1'),
            'dbPort' => $this->config->int('database.platform.port', 3306),
            'revealedPassword' => $this->session->getFlash('_revealed_db_password'),
            'apiUrl' => $this->apiUrl($project),
        ]);
    }

    public function revealPassword(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $project = $this->project($request);
        $this->session->flash('_revealed_db_password', $this->projects->revealDatabasePassword($project, $user['id'], $request->ip()));

        return $this->responder->redirect('/projects/' . $project['ref'] . '/database');
    }

    public function keys(Request $request): Response
    {
        $project = $this->project($request);
        $new = $this->session->getFlash(self::NEW_KEYS);

        return $this->responder->view($request, 'projects/show', [
            'project' => $project,
            'tab' => 'keys',
            'apiKeys' => $this->keys->forProject($project['id']),
            'newKeys' => is_array($new) ? array_filter($new, 'is_string') : [],
            'apiUrl' => $this->apiUrl($project),
            'permissions' => ApiKeyService::PERMISSIONS,
        ]);
    }

    public function storeKey(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $project = $this->project($request);
        $data = Validator::validate($request->all(), [
            'key_name' => 'required|string|min:2|max:80',
            'key_type' => 'required|in:public,secret,custom',
            'expires_at' => 'nullable|date',
        ], ['key_name' => 'nom de la clé', 'key_type' => 'type', 'expires_at' => 'expiration']);
        $permissions = $request->input('permissions', []);
        $permissions = is_array($permissions) ? array_values(array_filter($permissions, 'is_string')) : [];
        $expires = is_string($data['expires_at'] ?? null) ? substr($data['expires_at'], 0, 10) . ' 23:59:59' : null;
        if ($expires !== null && strtotime($expires) < time()) {
            throw ValidationException::withMessage('expires_at', 'La date d\'expiration doit être dans le futur.');
        }
        $created = $this->keys->generate($project['id'], (string) $data['key_type'], (string) $data['key_name'], $permissions, $expires);
        $this->audit->record('api_key.created', $user['id'], 'project:' . $project['ref'], $request->ip(), ['key_id' => $created['id'], 'type' => $data['key_type']]);
        $this->session->flash(self::NEW_KEYS, [(string) $data['key_name'] => $created['key']]);

        return $this->responder->flash('success', 'Clé créée. Copiez-la maintenant : elle ne sera plus affichée.')->redirect('/projects/' . $project['ref'] . '/keys');
    }

    public function regenerateKey(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $project = $this->project($request);
        $created = $this->keys->regenerate($project['id'], (int) $request->route('key'));
        if ($created === null) {
            throw HttpException::notFound();
        }
        $this->audit->record('api_key.regenerated', $user['id'], 'project:' . $project['ref'], $request->ip(), ['old_key_id' => (int) $request->route('key'), 'new_key_id' => $created['id']]);
        $this->session->flash(self::NEW_KEYS, ['Nouvelle clé' => $created['key']]);

        return $this->responder->flash('success', 'Clé régénérée : l\'ancienne est refusée immédiatement.')->redirect('/projects/' . $project['ref'] . '/keys');
    }

    public function revokeKey(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $project = $this->project($request);
        if (!$this->keys->revoke($project['id'], (int) $request->route('key'))) {
            throw HttpException::notFound();
        }
        $this->audit->record('api_key.revoked', $user['id'], 'project:' . $project['ref'], $request->ip(), ['key_id' => (int) $request->route('key')]);

        return $this->responder->flash('success', 'Clé révoquée.')->redirect('/projects/' . $project['ref'] . '/keys');
    }

    public function settings(Request $request): Response
    {
        return $this->responder->view($request, 'projects/show', ['project' => $this->project($request), 'tab' => 'settings', 'apiUrl' => '']);
    }

    public function rename(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $project = $this->project($request);
        $data = Validator::validate($request->all(), ['name' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[\pL\pN][\pL\pN _\-\.\']*$/u']], ['name' => 'nom du projet']);
        $this->projects->rename($project, (string) $data['name'], $user['id'], $request->ip());

        return $this->responder->flash('success', 'Projet renommé.')->redirect('/projects/' . $project['ref'] . '/settings');
    }

    public function destroy(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $project = $this->project($request);
        $confirm = $request->string('confirm_name');
        if (!hash_equals($project['name'], $confirm)) {
            throw ValidationException::withMessage('confirm_name', 'Saisissez exactement le nom du projet pour confirmer la suppression.');
        }
        $this->projects->delete($project, $user['id'], $request->ip());

        return $this->responder->flash('success', 'Projet « ' . $project['name'] . ' » et sa base de données supprimés.')->redirect('/projects');
    }

    /**
     * @return ProjectRow
     */
    private function project(Request $request): array
    {
        $project = $this->projects->findAccessible(CurrentUser::from($request), (string) $request->route('ref'));
        if ($project === null) {
            // 404 aussi pour le projet d'un autre utilisateur : on ne révèle pas son existence.
            throw HttpException::notFound('Projet introuvable.');
        }

        return $project;
    }

    /**
     * @param ProjectRow $project
     *
     * @return array<string, int>
     */
    private function projectDaily(array $project): array
    {
        return $this->usage->dailyForProject($project['id'], 30);
    }

    /**
     * @param ProjectRow $project
     */
    private function apiUrl(array $project): string
    {
        return rtrim($this->config->string('app.url'), '/') . '/api/v1/' . $project['ref'];
    }
}
