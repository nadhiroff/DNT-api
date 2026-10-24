<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database\ConnectionFactory;
use App\Core\Database\Database;
use App\Repositories\ProjectRepository;
use App\Services\Crypto;
use App\Services\Projects\ProjectService;

/**
 * Projets : création réelle de bases et d'utilisateurs MySQL, isolation, clés API, quotas, suppression.
 */
final class ProjectFlowTest extends WebTestCase
{
    /** @var list<string> */
    private array $refs = [];

    protected function tearDown(): void
    {
        // Nettoyage des bases réellement créées sur le serveur MySQL.
        $projects = $this->app->container()->get(ProjectRepository::class);
        foreach ($this->refs as $ref) {
            $project = $projects->findByRef($ref);
            if ($project !== null) {
                $this->app->container()->get(ProjectService::class)->delete($project, 0, '127.0.0.1');
            }
        }
        parent::tearDown();
    }

    /**
     * @return array{ref: string, public: string, secret: string}
     */
    private function createProject(string $name): array
    {
        $response = $this->send('POST', '/projects', ['name' => $name]);
        self::assertSame(303, $response->status(), $response->body());
        self::assertMatchesRegularExpression('#^/projects/([a-z0-9]{20})$#', (string) $response->header('location'));
        $ref = substr((string) $response->header('location'), 10, 20);
        $this->refs[] = $ref;
        $page = $this->send('GET', '/projects/' . $ref . '/keys')->body();
        $pk = self::extract('/pk_live_[A-Za-z0-9\-_]{40,64}/', $page);
        $sk = self::extract('/sk_live_[A-Za-z0-9\-_]{40,64}/', $page);
        // Toujours copiables (stockées chiffrées), aussi depuis la vue d'ensemble (.env).
        self::assertStringContainsString($pk, $this->send('GET', '/projects/' . $ref . '/keys')->body());
        self::assertStringContainsString('NOVABASE_KEY=' . $pk, html_entity_decode($this->send('GET', '/projects/' . $ref)->body()));

        return ['ref' => $ref, 'public' => $pk, 'secret' => $sk];
    }

    private function projectDb(string $ref): Database
    {
        $project = $this->app->container()->get(ProjectRepository::class)->findByRef($ref);
        self::assertNotNull($project);
        $c = self::credentials();

        return new Database(ConnectionFactory::make([
            'host' => $c['host'], 'port' => $c['port'], 'database' => $project['db_name'], 'username' => $project['db_user'],
            'password' => $this->app->container()->get(Crypto::class)->decrypt($project['db_pass_enc'], 'project_db_password'),
        ]));
    }

    public function testProvisioningIsolationAndApiKeys(): void
    {
        $this->createUser('owner@example.com');
        $this->login('owner@example.com');
        $one = $this->createProject('Boutique');
        $two = $this->createProject('Blog');

        // La base et l'utilisateur existent ; l'utilisateur ne voit ni la plateforme ni l'autre projet.
        $db1 = $this->projectDb($one['ref']);
        $db1->execute('CREATE TABLE products (id INT PRIMARY KEY, name VARCHAR(50))');
        $visible = array_column($db1->select('SHOW DATABASES'), 'Database');
        self::assertContains('proj_' . $one['ref'], $visible);
        self::assertNotContains('proj_' . $two['ref'], $visible);
        self::assertNotContains((string) self::$database, $visible);
        try {
            $db1->select('SELECT * FROM `proj_' . $two['ref'] . '`.`information` LIMIT 1');
            self::fail('Accès à la base d\'un autre projet');
        } catch (\PDOException $e) {
            self::assertMatchesRegularExpression('/denied|1044|1142/i', $e->getMessage());
        }
        try {
            $db1->select('SELECT * FROM `' . self::$database . '`.users LIMIT 1');
            self::fail('Accès à la base plateforme');
        } catch (\PDOException $e) {
            self::assertMatchesRegularExpression('/denied|1044|1142/i', $e->getMessage());
        }

        // API : clé requise, clé d'un autre projet refusée, clé valide acceptée.
        $base = '/api/v1/' . $one['ref'];
        self::assertSame(401, $this->send('GET', $base)->status());
        self::assertSame(401, $this->send('GET', $base, [], ['apikey' => $two['public']])->status());
        $ok = $this->send('GET', $base, [], ['apikey' => $one['public']]);
        self::assertSame(200, $ok->status(), $ok->body());
        self::assertSame(['products'], json_decode($ok->body(), true)['tables']);
        self::assertNotNull($ok->header('x-ratelimit-limit'));

        // Régénération : l'ancienne clé est refusée immédiatement.
        $keyId = (int) self::db()->scalar("SELECT id FROM api_keys WHERE project_id = (SELECT id FROM projects WHERE ref = ?) AND type = 'public' AND revoked_at IS NULL", [$one['ref']]);
        $this->send('POST', '/projects/' . $one['ref'] . '/keys/' . $keyId . '/regenerate');
        $new = self::extract('/pk_live_[A-Za-z0-9\-_]{40,64}/', $this->send('GET', '/projects/' . $one['ref'] . '/keys')->body());
        self::assertSame(401, $this->send('GET', $base, [], ['apikey' => $one['public']])->status());
        self::assertSame(200, $this->send('GET', $base, [], ['apikey' => $new])->status());

        // Les requêtes (succès et erreurs) sont comptabilisées.
        self::assertSame(5, (int) self::db()->scalar('SELECT SUM(requests) FROM api_usage_hourly u JOIN projects p ON p.id = u.project_id WHERE p.ref = ?', [$one['ref']]));
        self::assertStringContainsString('Boutique', $this->send('GET', '/console')->body());

        // Quota : surcharge individuelle à 2 projets (le plan Gratuit en autorise 5).
        self::db()->execute("INSERT INTO user_limits (user_id, limit_key, value) SELECT id, 'projects', 2 FROM users WHERE email = 'owner@example.com'");
        $third = $this->send('POST', '/projects', ['name' => 'Troisième']);
        self::assertSame(303, $third->status());
        self::assertStringContainsString('limite de 2 projet', $this->send('GET', '/projects/new')->body());
    }

    public function testOtherUsersCannotSeeOrDeleteAProject(): void
    {
        $this->createUser('alice@example.com');
        $this->createUser('bob@example.com');
        $this->login('alice@example.com');
        $project = $this->createProject('Privé');

        $this->send('POST', '/logout');
        $this->login('bob@example.com');
        foreach (['', '/database', '/keys', '/settings'] as $suffix) {
            self::assertSame(404, $this->send('GET', '/projects/' . $project['ref'] . $suffix)->status(), $suffix);
        }
        self::assertSame(404, $this->send('POST', '/projects/' . $project['ref'] . '/delete', ['confirm_name' => 'Privé'])->status());
        self::assertSame(404, $this->send('POST', '/projects/' . $project['ref'] . '/database/reveal')->status());
        self::assertNotNull($this->app->container()->get(ProjectRepository::class)->findByRef($project['ref']));
    }

    public function testDeletionDropsDatabaseAndUser(): void
    {
        $this->createUser('del@example.com');
        $this->login('del@example.com');
        $project = $this->createProject('Éphémère');
        $wrong = $this->send('POST', '/projects/' . $project['ref'] . '/delete', ['confirm_name' => 'Autre']);
        self::assertSame(303, $wrong->status());
        self::assertNotNull($this->app->container()->get(ProjectRepository::class)->findByRef($project['ref']));

        self::assertSame('/projects', $this->send('POST', '/projects/' . $project['ref'] . '/delete', ['confirm_name' => 'Éphémère'])->header('location'));
        self::assertNull($this->app->container()->get(ProjectRepository::class)->findByRef($project['ref']));
        $server = new Database(ConnectionFactory::make(self::credentials()));
        self::assertNull($server->scalar('SELECT 1 FROM information_schema.SCHEMATA WHERE schema_name = ?', ['proj_' . $project['ref']]));
        self::assertNull($server->scalar('SELECT 1 FROM mysql.user WHERE user = ?', ['u_' . $project['ref']]));
    }

    public function testSuperadminIsUnlimitedAndCanSuspend(): void
    {
        $this->loginSuperadmin();
        $refs = [];
        for ($i = 1; $i <= 3; ++$i) {
            $refs[] = $this->createProject('Admin ' . $i);
        }
        self::assertCount(3, $refs, 'aucune limite pour le superadmin');
        self::assertSame(200, $this->send('GET', '/admin/projects')->status());
        $this->send('POST', '/admin/projects/' . $refs[0]['ref'] . '/suspend');
        self::assertSame(403, $this->send('GET', '/api/v1/' . $refs[0]['ref'], [], ['apikey' => $refs[0]['secret']])->status());
        $this->send('POST', '/admin/projects/' . $refs[0]['ref'] . '/suspend');
        self::assertSame(200, $this->send('GET', '/api/v1/' . $refs[0]['ref'], [], ['apikey' => $refs[0]['secret']])->status());
    }
}
