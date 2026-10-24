<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Core\Validation\ValidationException;
use App\Repositories\ApiUsageRepository;
use App\Repositories\ProjectDatabaseInspector;
use App\Repositories\ProjectRepository;
use App\Services\AuditService;
use App\Services\Crypto;
use App\Services\QuotaService;

/**
 * Cycle de vie des projets : création (quota + provisioning + clés par défaut), lecture,
 * renommage, suppression.
 *
 * @phpstan-import-type ProjectRow from ProjectRepository
 * @phpstan-import-type UserRow from \App\Repositories\UserRepository
 */
final class ProjectService
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly ProjectProvisioner $provisioner,
        private readonly ApiKeyService $keys,
        private readonly QuotaService $quotas,
        private readonly ProjectDatabaseInspector $inspector,
        private readonly ApiUsageRepository $usage,
        private readonly AuditService $audit,
        private readonly Crypto $crypto,
    ) {
    }

    /**
     * @param UserRow $owner
     *
     * @return array{project: ProjectRow, keys: array{public: string, secret: string}}
     *
     * @throws ValidationException quota atteint
     */
    public function create(array $owner, string $name, string $ip): array
    {
        $limit = $this->quotas->limit($owner, 'projects');
        if ($limit !== null && $this->projects->countForOwner($owner['id']) >= $limit) {
            throw ValidationException::withMessage('name', 'Vous avez atteint la limite de ' . $limit . ' projet(s) de votre offre.', ['name' => $name]);
        }
        $project = $this->provisioner->provision($owner['id'], $name);
        $public = $this->keys->generate($project['id'], 'public', 'Clé publique par défaut');
        $secret = $this->keys->generate($project['id'], 'secret', 'Clé secrète par défaut');
        $this->audit->record('project.created', $owner['id'], 'project:' . $project['ref'], $ip, ['name' => $name]);

        return ['project' => $project, 'keys' => ['public' => $public['key'], 'secret' => $secret['key']]];
    }

    /**
     * Projet accessible par cet utilisateur (propriétaire, ou superadmin), sinon null.
     *
     * @param UserRow $user
     *
     * @return ProjectRow|null
     */
    public function findAccessible(array $user, string $ref): ?array
    {
        if (preg_match('/^[a-z0-9]{20}$/', $ref) !== 1) {
            return null;
        }
        $project = $this->projects->findByRef($ref);
        if ($project === null || $project['status'] === 'failed') {
            return null;
        }

        return $project['owner_id'] === $user['id'] || $user['role'] === 'superadmin' ? $project : null;
    }

    /**
     * @param UserRow $user
     *
     * @return list<array{project: ProjectRow, bytes: int, requests: int}>
     */
    public function listForOwner(array $user): array
    {
        $projects = $this->projects->forOwner($user['id']);
        $sizes = $this->inspector->sizes(array_column($projects, 'db_name'));

        return array_map(fn (array $p): array => [
            'project' => $p,
            'bytes' => $sizes[$p['db_name']] ?? 0,
            'requests' => $this->usage->totalForProject($p['id']),
        ], $projects);
    }

    /**
     * @param ProjectRow $project
     *
     * @return array{bytes: int, tables: list<array{name: string, rows: int, bytes: int, created_at: string|null}>}
     */
    public function database(array $project): array
    {
        return $this->inspector->describe($project['db_name']);
    }

    /**
     * @param ProjectRow $project
     */
    public function revealDatabasePassword(array $project, int $actorId, string $ip): string
    {
        $this->audit->record('project.db_password_revealed', $actorId, 'project:' . $project['ref'], $ip);

        return $this->crypto->decrypt($project['db_pass_enc'], 'project_db_password');
    }

    /**
     * @param ProjectRow $project
     */
    public function rename(array $project, string $name, int $actorId, string $ip): void
    {
        $this->projects->rename($project['id'], $name);
        $this->audit->record('project.renamed', $actorId, 'project:' . $project['ref'], $ip, ['name' => $name]);
    }

    /**
     * Suppression définitive : base, utilisateur MySQL, clés, statistiques.
     *
     * @param ProjectRow $project
     */
    public function delete(array $project, int $actorId, string $ip): void
    {
        $this->provisioner->deprovision($project);
        $this->projects->delete($project['id']);
        $this->audit->record('project.deleted', $actorId, 'project:' . $project['ref'], $ip, ['name' => $project['name'], 'owner_id' => $project['owner_id']]);
    }

    /**
     * @param ProjectRow $project
     */
    public function setSuspended(array $project, bool $suspended, int $actorId, string $ip): void
    {
        $this->projects->setStatus($project['id'], $suspended ? 'suspended' : 'active');
        $this->audit->record($suspended ? 'project.suspended' : 'project.reactivated', $actorId, 'project:' . $project['ref'], $ip);
    }
}
