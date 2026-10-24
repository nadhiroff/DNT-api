<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Core\Config;
use App\Core\Database\ConnectionFactory;
use App\Core\Database\Database;
use App\Core\Database\DatabaseManager;
use App\Core\Database\Identifier;
use App\Core\Log\Logger;
use App\Repositories\ProjectRepository;
use App\Services\Crypto;
use Throwable;

/**
 * Création d'un projet : une base proj_<ref> et un utilisateur MySQL u_<ref> qui n'a de droits
 * que sur cette base. Chaque étape réussie est annulée si une étape suivante échoue
 * (le DDL MySQL n'est pas transactionnel).
 *
 * @phpstan-import-type ProjectRow from ProjectRepository
 */
final class ProjectProvisioner
{
    private const string ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';

    /** Droits accordés à l'utilisateur du projet, sur sa base uniquement. */
    private const string GRANTS = 'SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES';

    public function __construct(
        private readonly DatabaseManager $databases,
        private readonly ProjectRepository $projects,
        private readonly Crypto $crypto,
        private readonly Config $config,
        private readonly Logger $logger,
    ) {
    }

    /**
     * @return ProjectRow
     */
    public function provision(int $ownerId, string $name): array
    {
        $ref = $this->uniqueRef();
        $dbName = 'proj_' . $ref;
        $dbUser = 'u_' . $ref;
        $password = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $host = $this->config->string('database.project_user_host', 'localhost');

        $projectId = $this->projects->create([
            'ref' => $ref,
            'owner_id' => $ownerId,
            'name' => $name,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_pass_enc' => $this->crypto->encrypt($password, 'project_db_password'),
            'jwt_secret_enc' => $this->crypto->encrypt(bin2hex(random_bytes(32)), 'project_jwt_secret'),
        ]);

        $admin = $this->databases->admin();
        $pdo = $admin->pdo();
        $done = [];
        try {
            $admin->execute('CREATE DATABASE ' . Identifier::quote($dbName) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
            $done[] = 'database';
            $admin->execute('CREATE USER ' . Identifier::literal($pdo, $dbUser) . '@' . Identifier::literal($pdo, $host) . ' IDENTIFIED BY ' . Identifier::literal($pdo, $password));
            $done[] = 'user';
            $admin->execute('GRANT ' . self::GRANTS . ' ON ' . Identifier::quote($dbName) . '.* TO ' . Identifier::literal($pdo, $dbUser) . '@' . Identifier::literal($pdo, $host));

            // Vérification : le nouvel utilisateur se connecte et ne voit pas la base plateforme.
            $this->verify($dbName, $dbUser, $password);
            $this->projects->setStatus($projectId, 'active');
        } catch (Throwable $e) {
            $this->rollback($done, $dbName, $dbUser, $host);
            $this->projects->setStatus($projectId, 'failed');
            $this->logger->error('Échec du provisioning du projet', ['ref' => $ref, 'exception' => $e]);

            throw new ProvisioningException('La création de la base du projet a échoué. Réessayez dans un instant.', 0, $e);
        }

        $project = $this->projects->findById($projectId);
        if ($project === null) {
            throw new ProvisioningException('Projet introuvable après sa création.');
        }

        return $project;
    }

    /**
     * Supprime la base et l'utilisateur MySQL du projet (les métadonnées sont supprimées par l'appelant).
     *
     * @param ProjectRow $project
     */
    public function deprovision(array $project): void
    {
        $host = $this->config->string('database.project_user_host', 'localhost');
        $admin = $this->databases->admin();
        $pdo = $admin->pdo();
        $admin->execute('DROP DATABASE IF EXISTS ' . Identifier::quote($project['db_name']));
        $admin->execute('DROP USER IF EXISTS ' . Identifier::literal($pdo, $project['db_user']) . '@' . Identifier::literal($pdo, $host));
    }

    /**
     * Connexion au nom de l'utilisateur du projet (jamais avec le compte d'administration).
     *
     * @param ProjectRow $project
     */
    public function connect(array $project): Database
    {
        $password = $this->crypto->decrypt($project['db_pass_enc'], 'project_db_password');

        return new Database(fn (): \PDO => ConnectionFactory::make($this->projectConfig($project['db_name'], $project['db_user'], $password)));
    }

    private function verify(string $dbName, string $dbUser, string $password): void
    {
        $db = new Database(ConnectionFactory::make($this->projectConfig($dbName, $dbUser, $password)));
        $visible = array_column($db->select('SHOW DATABASES'), 'Database');
        if (in_array($this->config->string('database.platform_database', 'novabase'), $visible, true)) {
            throw new ProvisioningException('L\'utilisateur du projet voit la base plateforme : droits trop larges.');
        }
    }

    /**
     * @return array{host: string, port: int|string, socket: string|null, database: string, username: string, password: string}
     */
    private function projectConfig(string $dbName, string $dbUser, string $password): array
    {
        /** @var array{host?: string, port?: int|string, socket?: string|null} $platform */
        $platform = $this->config->array('database.platform');

        return [
            'host' => $platform['host'] ?? '127.0.0.1',
            'port' => $platform['port'] ?? 3306,
            'socket' => $platform['socket'] ?? null,
            'database' => $dbName,
            'username' => $dbUser,
            'password' => $password,
        ];
    }

    /**
     * @param list<string> $done
     */
    private function rollback(array $done, string $dbName, string $dbUser, string $host): void
    {
        $admin = $this->databases->admin();
        try {
            if (in_array('user', $done, true)) {
                $admin->execute('DROP USER IF EXISTS ' . Identifier::literal($admin->pdo(), $dbUser) . '@' . Identifier::literal($admin->pdo(), $host));
            }
            if (in_array('database', $done, true)) {
                $admin->execute('DROP DATABASE IF EXISTS ' . Identifier::quote($dbName));
            }
        } catch (Throwable $e) {
            $this->logger->critical('Rollback du provisioning incomplet : nettoyage manuel requis', ['db' => $dbName, 'user' => $dbUser, 'exception' => $e]);
        }
    }

    private function uniqueRef(): string
    {
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            // Première position : une lettre, pour un identifiant MySQL sans ambiguïté.
            $ref = self::ALPHABET[random_int(0, 25)];
            for ($i = 1; $i < 20; ++$i) {
                $ref .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            if (!$this->projects->refExists($ref)) {
                return $ref;
            }
        }

        throw new ProvisioningException('Impossible de générer un identifiant de projet unique.');
    }
}
