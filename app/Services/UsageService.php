<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Http\HttpException;
use App\Repositories\ProjectDatabaseInspector;
use App\Repositories\UsageRepository;
use App\Repositories\UserRepository;

/**
 * Application des quotas : les écritures (insertion, modification, envoi d'e-mail) sont refusées
 * en 402 quand une limite est atteinte ; la lecture et la suppression restent toujours permises.
 */
final class UsageService
{
    public function __construct(
        private readonly UsageRepository $usage,
        private readonly UserRepository $users,
        private readonly QuotaService $quotas,
        private readonly ProjectDatabaseInspector $inspector,
    ) {
    }

    /**
     * @param array{owner_id: int} $project
     *
     * @throws HttpException 402
     */
    public function assertCanWrite(array $project): void
    {
        $owner = $this->users->findById($project['owner_id']);
        if ($owner === null) {
            return;
        }
        $db = $this->quotas->limit($owner, 'db_storage_bytes');
        if ($db !== null && $this->usage->dbBytesForOwner($owner['id']) >= $db) {
            throw self::exceeded('db_storage', 'Espace de base de données de votre offre épuisé : supprimez des données ou changez d\'offre.');
        }
        $requests = $this->quotas->limit($owner, 'api_requests_month');
        if ($requests !== null && $this->usage->apiRequestsThisMonth($owner['id']) >= $requests) {
            throw self::exceeded('api_requests', 'Quota mensuel de requêtes API atteint.');
        }
    }

    /**
     * @param array{owner_id: int} $project
     *
     * @throws HttpException 402
     */
    public function assertCanSendEmail(array $project, int $count): void
    {
        $owner = $this->users->findById($project['owner_id']);
        if ($owner === null) {
            return;
        }
        $limit = $this->quotas->limit($owner, 'emails_day');
        if ($limit !== null && $this->usage->emailsToday($owner['id']) + $count > $limit) {
            throw self::exceeded('emails_day', 'Quota quotidien d\'e-mails atteint (' . $limit . ' par jour).');
        }
    }

    /**
     * Recalcule la taille de chaque base (tâche périodique, jamais à chaque appel API).
     */
    public function recalculate(): int
    {
        $projects = $this->usage->activeProjects();
        $sizes = $this->inspector->sizes(array_column($projects, 'db_name'));
        foreach ($projects as $project) {
            $this->usage->saveSnapshot($project['id'], $project['owner_id'], $sizes[$project['db_name']] ?? 0);
        }

        return count($projects);
    }

    private static function exceeded(string $limit, string $message): HttpException
    {
        return new HttpException(402, 'quota_exceeded', $message, [], ['limit' => $limit]);
    }
}
