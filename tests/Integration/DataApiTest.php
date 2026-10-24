<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Repositories\ProjectRepository;
use App\Services\MaintenanceService;
use App\Services\Projects\ProjectService;

/**
 * Table Editor + API REST + e-mails + clés, sur une vraie base MySQL de projet.
 */
final class DataApiTest extends WebTestCase
{
    private string $ref = '';

    private string $pk = '';

    private string $sk = '';

    protected function setUp(): void
    {
        parent::setUp();
        self::db()->execute("DELETE FROM users WHERE email = 'dev@example.com' OR role = 'superadmin'");
        $this->createUser('dev@example.com');
        $this->login('dev@example.com');
        $response = $this->send('POST', '/projects', ['name' => 'Boutique']);
        $this->ref = substr((string) $response->header('location'), 10, 20);
        $keys = $this->send('GET', '/projects/' . $this->ref . '/keys')->body();
        $this->pk = self::extract('/pk_live_[A-Za-z0-9\-_]{40,64}/', $keys);
        $this->sk = self::extract('/sk_live_[A-Za-z0-9\-_]{40,64}/', $keys);
    }

    protected function tearDown(): void
    {
        $project = $this->app->container()->get(ProjectRepository::class)->findByRef($this->ref);
        if ($project !== null) {
            $this->app->container()->get(ProjectService::class)->delete($project, 0, '127.0.0.1');
        }
        parent::tearDown();
    }

    /**
     * @param array<string, mixed>|list<mixed>|null $json
     * @param array<string, string>                 $headers
     */
    private function api(string $method, string $path, ?array $json = null, ?string $key = null, array $headers = []): \App\Core\Http\Response
    {
        $server = [
            'REQUEST_METHOD' => $method, 'REQUEST_URI' => '/api/v1/' . $this->ref . $path, 'SCRIPT_NAME' => '/index.php',
            'REMOTE_ADDR' => '198.51.100.9', 'HTTP_HOST' => 'nb.test', 'CONTENT_TYPE' => 'application/json',
            'HTTP_APIKEY' => $key ?? $this->sk,
        ];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }
        parse_str((string) parse_url($path, PHP_URL_QUERY), $query);
        /** @var array<string, mixed> $query */

        return $this->app->handle(new \App\Core\Http\Request(query: $query, server: $server, rawBody: $json !== null ? (string) json_encode($json) : ''));
    }

    /** @return array<mixed> */
    private static function json(\App\Core\Http\Response $response): array
    {
        $data = json_decode($response->body(), true);
        self::assertIsArray($data, $response->body());

        return $data;
    }

    private function createProductsTable(): void
    {
        $response = $this->send('POST', '/projects/' . $this->ref . '/tables', [
            'name' => 'produits',
            'pk_type' => 'bigint',
            'columns' => [
                ['name' => 'nom', 'type' => 'string', 'length' => '80', 'is_unique' => '1'],
                ['name' => 'prix', 'type' => 'decimal', 'default_value' => '0'],
                ['name' => 'stock', 'type' => 'integer', 'nullable' => '1'],
                ['name' => 'actif', 'type' => 'boolean', 'default_value' => '1'],
                ['name' => 'details', 'type' => 'json', 'nullable' => '1'],
            ],
        ]);
        self::assertSame('/projects/' . $this->ref . '/tables/produits', $response->header('location'), $response->body());
    }

    public function testSqlEditorCreatesTablesExposedByTheApi(): void
    {
        $sqlPath = '/projects/' . $this->ref . '/sql';
        self::assertStringContainsString('Éditeur SQL', $this->send('GET', $sqlPath)->body());

        $script = "-- Catalogue créé en SQL\nCREATE TABLE categories (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, nom VARCHAR(60) NOT NULL UNIQUE);\n"
            . "CREATE TABLE articles (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, titre VARCHAR(120) NOT NULL, prix DECIMAL(10,2) NOT NULL DEFAULT 0,\n"
            . "  publie BOOLEAN NOT NULL DEFAULT FALSE, categorie_id BIGINT UNSIGNED NULL, FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE SET NULL);\n"
            . "CREATE TABLE journal (code VARCHAR(10) PRIMARY KEY);\n"
            . "INSERT INTO categories (nom) VALUES ('Livres'), ('Musique');\n"
            . "SELECT id, nom FROM categories ORDER BY id;";
        $page = $this->send('POST', $sqlPath, ['sql' => $script]);
        self::assertSame(200, $page->status(), $page->body());
        $body = $page->body();
        self::assertStringContainsString('5 / 5 instructions réussies', $body);
        self::assertStringContainsString('Nouvelles tables disponibles dans l&#039;API', $body);
        self::assertStringContainsString('/data/articles', $body);
        self::assertStringContainsString('non exposée dans l\'API', $body);
        self::assertStringContainsString('Musique', $body);
        self::assertStringContainsString('2 lignes affectées', $body);

        // L'API voit immédiatement les tables créées en SQL, avec leurs types et leur relation.
        $categories = self::json($this->api('GET', '/data/categories?order=id.asc'));
        self::assertSame(['Livres', 'Musique'], array_column($categories, 'nom'));
        self::assertArrayHasKey('created_at', $categories[0]);
        $created = $this->api('POST', '/data/articles', ['titre' => 'Roman', 'prix' => 12.5, 'publie' => true, 'categorie_id' => $categories[0]['id']]);
        self::assertSame(201, $created->status(), $created->body());
        $article = self::json($created);
        self::assertSame('12.50', $article['prix']);
        self::assertTrue($article['publie']);
        self::assertSame(422, $this->api('POST', '/data/articles', ['titre' => 'X', 'categorie_id' => 999])->status());
        self::assertSame(409, $this->api('POST', '/data/categories', ['nom' => 'Livres'])->status());
        self::assertSame(404, $this->api('GET', '/data/journal')->status());

        // Modification de structure : ALTER puis DROP mettent l'API à jour.
        $this->send('POST', $sqlPath, ['sql' => 'ALTER TABLE articles ADD COLUMN stock INT NOT NULL DEFAULT 0']);
        self::assertSame(7, self::json($this->api('PATCH', '/data/articles/' . $article['id'], ['stock' => 7]))['stock']);
        $this->send('POST', $sqlPath, ['sql' => 'DROP TABLE articles']);
        self::assertSame(404, $this->api('GET', '/data/articles')->status());

        // Erreur MySQL : message affiché, instructions suivantes non exécutées, historique.
        $error = $this->send('POST', $sqlPath, ['sql' => "INSERT INTO inconnue VALUES (1);\nDELETE FROM categories;"])->body();
        self::assertStringContainsString('[1146]', $error);
        self::assertStringContainsString('Non exécutée', $error);
        self::assertCount(2, self::json($this->api('GET', '/data/categories')));
        self::assertStringContainsString('Historique des modifications', $error);

        // Garde-fous : changement de base, fonctions bloquantes, isolation MySQL.
        $use = $this->send('POST', $sqlPath, ['sql' => 'USE information_schema']);
        self::assertSame(303, $use->status());
        $this->send('POST', $sqlPath, ['sql' => 'SELECT SLEEP(5)']);
        $isolation = $this->send('POST', $sqlPath, ['sql' => 'SELECT COUNT(*) FROM ' . self::db()->scalar('SELECT DATABASE()') . '.users'])->body();
        self::assertStringContainsString('[1142]', $isolation);
        self::assertCount(2, self::json($this->api('GET', '/data/categories')));
    }

    public function testTableEditorValidatesIdentifiers(): void
    {
        foreach (['select', '1abc', 'nb_secret', 'a;drop', 'x`y', 'é_table'] as $bad) {
            $this->send('POST', '/projects/' . $this->ref . '/tables', ['name' => $bad, 'columns' => [['name' => 'a', 'type' => 'string']]]);
            self::assertSame(0, (int) self::db()->scalar('SELECT COUNT(*) FROM table_definitions'), $bad);
        }
        $this->send('POST', '/projects/' . $this->ref . '/tables', ['name' => 'ok_table', 'columns' => [['name' => 'id', 'type' => 'string']]]);
        self::assertStringContainsString('nom réservé', $this->send('GET', '/projects/' . $this->ref . '/tables/new')->body());
        $this->send('POST', '/projects/' . $this->ref . '/tables', ['name' => 'ok_table', 'columns' => [['name' => 'note', 'type' => 'integer', 'default_value' => "1; DROP TABLE x"]]]);
        self::assertSame([], self::db()->select('SELECT name FROM table_definitions'));

        // Les majuscules sont simplement converties.
        $this->send('POST', '/projects/' . $this->ref . '/tables', ['name' => 'Clients', 'columns' => [['name' => 'Email', 'type' => 'string']]]);
        self::assertSame('clients', self::db()->scalar('SELECT name FROM table_definitions'));
        self::assertSame('email', self::db()->scalar('SELECT name FROM column_definitions'));
    }

    public function testCrudFiltersAndRules(): void
    {
        $this->createProductsTable();
        self::assertSame('success', self::db()->scalar("SELECT status FROM schema_changes WHERE action = 'create_table'"));

        // Création (clé secrète), assignation de masse ignorée.
        $created = $this->api('POST', '/data/produits', ['nom' => 'Café', 'prix' => 15.5, 'stock' => 10, 'details' => ['origine' => 'Éthiopie'], 'id' => 999, 'owner_id' => 7]);
        self::assertSame(201, $created->status(), $created->body());
        $row = self::json($created);
        self::assertNotSame(999, $row['id']);
        self::assertNull($row['owner_id']);
        self::assertSame('15.50', $row['prix']);
        self::assertTrue($row['actif']);
        self::assertSame(['origine' => 'Éthiopie'], $row['details']);

        $bulk = $this->api('POST', '/data/produits', [['nom' => 'Thé', 'prix' => 8, 'stock' => 0], ['nom' => 'Miel', 'prix' => 25, 'stock' => 3]]);
        self::assertSame(201, $bulk->status());
        self::assertCount(2, self::json($bulk));

        // Lecture filtrée, triée, paginée.
        $list = $this->api('GET', '/data/produits?prix=gte.10&order=prix.desc&select=id,nom,prix');
        self::assertSame('2', $list->header('x-total-count'));
        self::assertSame(['Miel', 'Café'], array_column(self::json($list), 'nom'));
        self::assertSame(['id', 'nom', 'prix'], array_keys(self::json($list)[0]));
        self::assertSame(['Thé'], array_column(self::json($this->api('GET', '/data/produits?nom=ilike.*th*')), 'nom'));
        self::assertCount(2, self::json($this->api('GET', '/data/produits?nom=in.(Café,Miel)')));
        self::assertCount(1, self::json($this->api('GET', '/data/produits?limit=1&offset=1')));
        self::assertSame(400, $this->api('GET', '/data/produits?prix=foo.1')->status());
        self::assertSame(400, $this->api('GET', '/data/produits?inconnue=eq.1')->status());
        self::assertSame(400, $this->api('GET', '/data/produits?order=nom;DROP')->status());
        self::assertSame(404, $this->api('GET', '/data/inexistante')->status());

        // Validation, contrainte unique, mise à jour, suppression.
        self::assertSame(422, $this->api('POST', '/data/produits', ['nom' => 'X', 'stock' => 'beaucoup'])->status());
        self::assertSame(409, $this->api('POST', '/data/produits', ['nom' => 'Café'])->status());
        $patched = $this->api('PATCH', '/data/produits/' . $row['id'], ['stock' => 42, 'created_at' => '2000-01-01']);
        self::assertSame(42, self::json($patched)['stock']);
        self::assertNotSame('2000-01-01 00:00:00', self::json($patched)['created_at']);
        self::assertSame(204, $this->api('DELETE', '/data/produits/' . $row['id'])->status());
        self::assertSame(404, $this->api('GET', '/data/produits/' . $row['id'])->status());

        // Clé publique : lecture publique, écriture réservée à la clé secrète par défaut.
        self::assertSame(200, $this->api('GET', '/data/produits', null, $this->pk)->status());
        self::assertSame(403, $this->api('POST', '/data/produits', ['nom' => 'Pirate'], $this->pk)->status());
        $this->send('POST', '/projects/' . $this->ref . '/tables/produits/rules', ['rule_read' => 'secret', 'rule_insert' => 'public', 'rule_update' => 'secret', 'rule_delete' => 'secret']);
        self::assertSame(403, $this->api('GET', '/data/produits', null, $this->pk)->status());
        self::assertSame(201, $this->api('POST', '/data/produits', ['nom' => 'Public'], $this->pk)->status());
        $this->send('POST', '/projects/' . $this->ref . '/tables/produits/rules', ['rule_read' => 'owner', 'rule_insert' => 'secret', 'rule_update' => 'secret', 'rule_delete' => 'secret']);
        self::assertSame(401, $this->api('GET', '/data/produits', null, $this->pk)->status());

        // CORS (pré-vérification et en-têtes sur les erreurs).
        $preflight = $this->api('OPTIONS', '/data/produits', null, null, ['origin' => 'https://monapp.com']);
        self::assertSame(204, $preflight->status());
        self::assertSame('https://monapp.com', $preflight->header('access-control-allow-origin'));
        self::assertSame('https://monapp.com', $this->api('GET', '/data/produits', null, 'pk_live_faux', ['origin' => 'https://monapp.com'])->header('access-control-allow-origin'));
        $this->send('POST', '/projects/' . $this->ref . '/settings/cors', ['cors_origins' => "https://autorise.com"]);
        self::assertNull($this->api('GET', '/data/produits', null, null, ['origin' => 'https://monapp.com'])->header('access-control-allow-origin'));

        // OpenAPI généré depuis le schéma.
        $spec = self::json($this->api('GET', '/openapi.json'));
        self::assertArrayHasKey('/data/produits', $spec['paths']);
        self::assertSame(['type' => 'string', 'format' => 'decimal'], $spec['components']['schemas']['Produits']['properties']['prix']);
    }

    public function testConsoleGridRelationsCsvAndDrop(): void
    {
        $this->createProductsTable();
        $base = '/projects/' . $this->ref . '/tables/produits';
        self::assertSame($base, $this->send('POST', $base . '/rows', ['f_nom' => 'Bissap', 'f_prix' => '800', 'f_stock' => '', 'f_actif' => '1', 'f_details' => ''])->header('location'));
        $grid = $this->send('GET', $base)->body();
        self::assertStringContainsString('Bissap', $grid);
        $this->send('POST', $base . '/rows', ['f_nom' => 'Bissap', 'f_prix' => '1']);
        self::assertStringContainsString('Une valeur unique existe déjà', $this->send('GET', $base . '/rows/new')->body());
        $this->send('POST', $base . '/rows', ['f_nom' => 'Autre', 'f_prix' => 'pas un prix']);
        self::assertStringContainsString('aria-invalid="true"', $this->send('GET', $base . '/rows/new')->body());

        // Relation : commandes.produit_id → produits.id (RESTRICT).
        $this->send('POST', '/projects/' . $this->ref . '/tables', ['name' => 'commandes', 'columns' => [['name' => 'produit_id', 'type' => 'foreign', 'fk_table' => 'produits', 'fk_on_delete' => 'RESTRICT'], ['name' => 'quantite', 'type' => 'integer', 'default_value' => '1']]]);
        $productId = (int) self::json($this->api('GET', '/data/produits?nom=eq.Bissap'))[0]['id'];
        self::assertSame(422, $this->api('POST', '/data/commandes', ['produit_id' => 999999])->status());
        self::assertSame(201, $this->api('POST', '/data/commandes', ['produit_id' => $productId])->status());
        self::assertSame(409, $this->api('DELETE', '/data/produits/' . $productId)->status());
        $this->send('POST', $base . '/delete', ['confirm_table' => 'produits']);
        self::assertNotNull(self::db()->scalar("SELECT 1 FROM table_definitions WHERE name = 'produits'"), 'table référencée non supprimable');

        // CSV : export puis import (formules neutralisées à l'export).
        $this->api('POST', '/data/produits', ['nom' => '=1+1', 'prix' => 2]);
        $csv = $this->send('GET', $base . '/export.csv');
        self::assertStringContainsString('text/csv', (string) $csv->header('content-type'));
        self::assertStringContainsString("'=1+1", $csv->body());
        $file = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($file, "nom;prix;stock\nImport A;10;1\nImport B;20;2\n");
        $_FILES = [];
        $import = $this->app->handle(new \App\Core\Http\Request(
            post: ['_csrf' => $this->app->container()->get(\App\Core\Security\Csrf::class)->token()],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $base . '/import', 'SCRIPT_NAME' => '/index.php', 'REMOTE_ADDR' => '198.51.100.9', 'HTTP_HOST' => 'nb.test'],
            files: ['csv' => ['name' => 'p.csv', 'type' => 'text/csv', 'tmp_name' => $file, 'error' => UPLOAD_ERR_OK, 'size' => (int) filesize($file)]],
        ));
        self::assertSame(303, $import->status());
        self::assertSame('2', $this->api('GET', '/data/produits?nom=like.Import*')->header('x-total-count'));
        @unlink($file);

        // Suppression d'une table non référencée.
        $this->send('POST', '/projects/' . $this->ref . '/tables/commandes/delete', ['confirm_table' => 'commandes']);
        self::assertNull(self::db()->scalar("SELECT 1 FROM table_definitions WHERE name = 'commandes'"));
        self::assertSame(404, $this->api('GET', '/data/commandes')->status());
    }

    public function testEmailsKeysHistoryAndQuota(): void
    {
        // Envoi par l'API : clé secrète exigée, mise en file, historique.
        self::assertSame(403, $this->api('POST', '/email/send', ['to' => 'a@b.co', 'subject' => 'x', 'body_text' => 'y'], $this->pk)->status());
        self::assertSame(422, $this->api('POST', '/email/send', ['to' => "a@b.co\r\nBcc: x@y.z", 'subject' => 'x', 'body_text' => 'y'])->status());
        self::assertSame(422, $this->api('POST', '/email/send', ['to' => 'a@b.co', 'subject' => 'x', 'body_text' => 'y', 'attachments' => [['filename' => 'virus.exe', 'content' => base64_encode('MZ')]]])->status());
        $sent = $this->api('POST', '/email/send', ['to' => 'client@example.com', 'subject' => 'Bienvenue', 'body_html' => '<p>Bonjour</p>', 'attachments' => [['filename' => 'facture.pdf', 'content' => base64_encode('%PDF-1.4'), 'content_type' => 'application/pdf']]]);
        self::assertSame(202, $sent->status(), $sent->body());
        $id = (int) self::json($sent)['id'];
        self::assertSame('sent', self::json($this->api('GET', '/email/' . $id))['status']);
        self::assertSame('client@example.com', $this->lastMail()['to']);
        self::assertStringContainsString('Bienvenue', $this->send('GET', '/projects/' . $this->ref . '/emails')->body());

        // SMTP du projet : hôte privé refusé (SSRF) pour un utilisateur.
        $this->send('POST', '/projects/' . $this->ref . '/emails/smtp', ['host' => 'localhost', 'port' => '25', 'encryption' => 'none', 'from_email' => 'x@example.com']);
        self::assertNull(self::db()->scalar('SELECT 1 FROM smtp_settings'));

        // Clés : régénération, historique 30 jours puis purge.
        $keyId = (int) self::db()->scalar("SELECT id FROM api_keys WHERE key_hash = ?", [hash('sha256', $this->pk)]);
        $this->send('POST', '/projects/' . $this->ref . '/keys/' . $keyId . '/regenerate');
        self::assertSame(401, $this->api('GET', '/', null, $this->pk)->status());
        self::assertStringContainsString('Supprimée dans 30 j', $this->send('GET', '/projects/' . $this->ref . '/keys')->body());
        self::db()->execute('UPDATE api_keys SET revoked_at = UTC_TIMESTAMP() - INTERVAL 31 DAY WHERE id = ?', [$keyId]);
        $this->app->container()->get(MaintenanceService::class)->run();
        self::assertNull(self::db()->scalar('SELECT 1 FROM api_keys WHERE id = ?', [$keyId]));
        $this->send('POST', '/projects/' . $this->ref . '/keys', ['key_name' => 'Serveur', 'key_type' => 'secret']);
        self::assertSame('["read","write","storage","email","bypass_rules"]', str_replace(' ', '', (string) self::db()->scalar("SELECT permissions FROM api_keys WHERE name = 'Serveur'")));

        // Quota : stockage épuisé → 402 sur l'écriture, lecture toujours permise.
        $this->createProductsTable();
        self::db()->execute("INSERT INTO user_limits (user_id, limit_key, value) SELECT id, 'db_storage_bytes', 1 FROM users WHERE email = 'dev@example.com'");
        $this->app->container()->get(MaintenanceService::class)->run();
        $blocked = $this->api('POST', '/data/produits', ['nom' => 'Trop']);
        self::assertSame(402, $blocked->status());
        self::assertSame('quota_exceeded', self::json($blocked)['error']['code']);
        self::assertSame(200, $this->api('GET', '/data/produits')->status());
    }

    public function testCustomPlatformEmailTemplate(): void
    {
        $this->send('POST', '/logout');
        $this->loginSuperadmin();
        $this->send('POST', '/admin/emails/templates/reset-password', ['subject' => 'Nouveau mot de passe pour {{nom}}', 'heading' => 'Salut {{nom}}', 'body' => "Clique vite.\n\n<script>alert(1)</script>", 'button_label' => 'Changer']);
        $this->send('POST', '/logout');
        $this->send('POST', '/forgot-password', ['email' => 'dev@example.com']);
        $mail = $this->lastMail();
        self::assertSame('Nouveau mot de passe pour Test user', $mail['subject']);
        self::assertStringContainsString('Salut Test user', $mail['html']);
        self::assertStringContainsString('&lt;script&gt;', $mail['html']);
        self::assertStringContainsString('/reset-password/', $mail['html']);
    }
}
