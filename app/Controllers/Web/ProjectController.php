<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\UrlGenerator;
use App\Core\Session\Session;
use App\Core\Http\Response;
use App\Core\Validation\ValidationException;
use App\Core\Validation\Validator;
use App\Repositories\ApiUsageRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\TableRepository;
use App\Services\AuditService;
use App\Services\Data\OpenApiGenerator;
use App\Services\Projects\ApiKeyService;
use App\Services\Projects\ProjectService;
use App\Services\Projects\ProvisioningException;
use App\Services\QuotaService;

/**
 * Projets : liste, création, vue d'ensemble (intégration .env), API, clés, paramètres (CORS, suppression).
 *
 * @phpstan-import-type ProjectRow from ProjectRepository
 */
final class ProjectController
{
    public function __construct(
        private readonly Responder $responder,
        private readonly ProjectService $projects,
        private readonly ProjectRepository $projectRepository,
        private readonly ApiKeyService $keys,
        private readonly TableRepository $tables,
        private readonly QuotaService $quotas,
        private readonly ApiUsageRepository $usage,
        private readonly AuditService $audit,
        private readonly UrlGenerator $urls,
        private readonly Session $session,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = CurrentUser::from($request);

        return $this->responder->view($request, 'projects/index', [
            'items' => $this->projects->listForOwner($user),
            'limit' => $this->quotas->limit($user, 'projects'),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = CurrentUser::from($request);

        return $this->responder->view($request, 'projects/create', [
            'limit' => $this->quotas->limit($user, 'projects'),
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

        return $this->responder->flash('success', 'Projet « ' . $result['project']['name'] . ' » créé : sa base de données et ses clés sont prêtes.')
            ->redirect('/projects/' . $result['project']['ref']);
    }

    public function show(Request $request): Response
    {
        $project = $this->project($request);
        $daily = $this->usage->dailyForProject($project['id'], 30);
        $database = $this->projects->database($project);

        return $this->responder->view($request, 'projects/overview', $this->common($project, 'overview') + [
            'database' => $database,
            'tableCount' => $this->tables->count($project['id']),
            'daily' => $daily,
            'requests30' => array_sum($daily),
            'keyCount' => count(array_filter($this->keys->forProject($project['id']), static fn (array $k): bool => $k['revoked_at'] === null)),
            'integration' => $this->keys->integrationKeys($project['id']),
        ]);
    }

    public function api(Request $request): Response
    {
        $project = $this->project($request);

        return $this->responder->view($request, 'projects/api', $this->common($project, 'api') + [
            'tables' => $this->tables->forProject($project['id']),
            'integration' => $this->keys->integrationKeys($project['id']),
        ]);
    }

    public function openapi(Request $request): Response
    {
        $project = $this->project($request);
        $spec = OpenApiGenerator::generate($project, $this->tables->forProject($project['id']), $this->apiUrl($project));

        return Response::json($spec, 200, ['Content-Disposition' => 'attachment; filename="openapi-' . $project['ref'] . '.json"']);
    }

    /**
     * Affiche une fois le mot de passe MySQL du projet (accès direct avancé), après confirmation.
     */
    public function revealPassword(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $project = $this->project($request);
        $this->session->flash('_revealed_db_password', $this->projects->revealDatabasePassword($project, $user['id'], $request->ip()));

        return $this->responder->redirect('/projects/' . $project['ref'] . '/tables#acces-direct');
    }

    public function keys(Request $request): Response
    {
        $project = $this->project($request);
        $keys = $this->keys->forProject($project['id']);
        $plain = [];
        foreach ($keys as $key) {
            if ($key['revoked_at'] === null) {
                $plain[$key['id']] = $this->keys->plain($key);
            }
        }

        return $this->responder->view($request, 'projects/keys', $this->common($project, 'keys') + ['apiKeys' => $keys, 'plain' => $plain]);
    }

    public function storeKey(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $project = $this->project($request);
        $data = Validator::validate($request->all(), [
            'key_name' => 'required|string|min:2|max:80',
            'key_type' => 'required|in:public,secret',
        ], ['key_name' => 'nom de la clé', 'key_type' => 'type']);
        $created = $this->keys->generate($project['id'], (string) $data['key_type'], (string) $data['key_name']);
        $this->audit->record('api_key.created', $user['id'], 'project:' . $project['ref'], $request->ip(), ['key_id' => $created['id'], 'type' => $data['key_type']]);

        return $this->responder->flash('success', 'Clé « ' . $data['key_name'] . ' » créée. Copiez-la avec le bouton à côté.')->redirect('/projects/' . $project['ref'] . '/keys');
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

        return $this->responder->flash('success', 'Clé régénérée : l\'ancienne est refusée immédiatement et reste dans l\'historique 30 jours.')->redirect('/projects/' . $project['ref'] . '/keys');
    }

    public function revokeKey(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $project = $this->project($request);
        if (!$this->keys->revoke($project['id'], (int) $request->route('key'))) {
            throw HttpException::notFound();
        }
        $this->audit->record('api_key.revoked', $user['id'], 'project:' . $project['ref'], $request->ip(), ['key_id' => (int) $request->route('key')]);

        return $this->responder->flash('success', 'Clé révoquée. Elle reste visible dans l\'historique pendant 30 jours.')->redirect('/projects/' . $project['ref'] . '/keys');
    }

    public function settings(Request $request): Response
    {
        $project = $this->project($request);

        return $this->responder->view($request, 'projects/settings', $this->common($project, 'settings') + [
            'cors' => $this->projectRepository->corsOrigins($project['ref']) ?? [],
        ]);
    }

    public function rename(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $project = $this->project($request);
        $data = Validator::validate($request->all(), ['name' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[\pL\pN][\pL\pN _\-\.\']*$/u']], ['name' => 'nom du projet']);
        $this->projects->rename($project, (string) $data['name'], $user['id'], $request->ip());

        return $this->responder->flash('success', 'Projet renommé.')->redirect('/projects/' . $project['ref'] . '/settings');
    }

    public function updateCors(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $project = $this->project($request);
        $origins = [];
        foreach (preg_split('/[\s,]+/', $request->string('cors_origins')) ?: [] as $origin) {
            $origin = rtrim(trim($origin), '/');
            if ($origin === '') {
                continue;
            }
            if (preg_match('#^https?://[A-Za-z0-9.\-]+(:\d{1,5})?$#', $origin) !== 1) {
                throw ValidationException::withMessage('cors_origins', 'Origine invalide : « ' . mb_substr($origin, 0, 80) . ' » (format https://domaine.com).');
            }
            $origins[] = $origin;
        }
        $origins = array_values(array_unique(array_slice($origins, 0, 50)));
        $this->projectRepository->setCorsOrigins($project['id'], $origins);
        $this->audit->record('project.cors_updated', $user['id'], 'project:' . $project['ref'], $request->ip(), ['count' => count($origins)]);

        return $this->responder->flash('success', $origins === [] ? 'CORS : toutes les origines sont autorisées.' : 'CORS : ' . count($origins) . ' origine(s) autorisée(s).')->redirect('/projects/' . $project['ref'] . '/settings');
    }

    public function destroy(Request $request): Response
    {
        $user = CurrentUser::from($request);
        $project = $this->project($request);
        if (!hash_equals($project['name'], $request->string('confirm_name'))) {
            throw ValidationException::withMessage('confirm_name', 'Saisissez exactement le nom du projet pour confirmer la suppression.');
        }
        $this->projects->delete($project, $user['id'], $request->ip());

        return $this->responder->flash('success', 'Projet « ' . $project['name'] . ' » et sa base de données supprimés.')->redirect('/projects');
    }

    /**
     * @param ProjectRow $project
     *
     * @return array<string, mixed>
     */
    private function common(array $project, string $tab): array
    {
        return ['project' => $project, 'tab' => $tab, 'apiUrl' => $this->apiUrl($project)];
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
     */
    private function apiUrl(array $project): string
    {
        return $this->urls->absolute('/api/v1/' . $project['ref']);
    }
}
