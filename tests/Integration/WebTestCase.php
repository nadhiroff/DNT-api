<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Application;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Security\Csrf;
use App\Core\Security\PasswordHasher;
use App\Core\Security\Totp;
use App\Core\Session\Session;
use App\Repositories\UserRepository;
use App\Services\Crypto;
use App\Services\Mail\MailTransport;

/**
 * Base des tests de parcours web : application branchée sur la base de test, session en mémoire,
 * e-mails écrits dans un dossier temporaire.
 */
abstract class WebTestCase extends DatabaseTestCase
{
    protected Application $app;

    protected string $mailDir;

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['MAIL_DRIVER'] = 'log';
        $_ENV['QUEUE_DRIVER'] = 'sync';
        $this->app = self::app();
        $this->mailDir = sys_get_temp_dir() . '/nb-mail-' . bin2hex(random_bytes(4));
        $this->app->container()->instance(MailTransport::class, new MailTransport($this->app->config(), $this->mailDir));
        $this->browser();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->mailDir . '/*') ?: []);
        if (is_dir($this->mailDir)) {
            rmdir($this->mailDir);
        }
        unset($_ENV['MAIL_DRIVER'], $_ENV['QUEUE_DRIVER']);
    }

    protected function browser(): void
    {
        $this->app->container()->get(Session::class)->startInMemory();
    }

    /**
     * @param array<string, mixed>  $post
     * @param array<string, string> $headers en-têtes HTTP (« apikey » => …)
     */
    protected function send(string $method, string $path, array $post = [], array $headers = [], string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0 Safari/537.36'): Response
    {
        if ($method !== 'GET' && !str_starts_with($path, '/api/')) {
            $post['_csrf'] = $this->app->container()->get(Csrf::class)->token();
        }
        $server = [
            'REQUEST_METHOD' => $method, 'REQUEST_URI' => $path, 'SCRIPT_NAME' => '/index.php',
            'REMOTE_ADDR' => '198.51.100.' . random_int(1, 200), 'HTTP_HOST' => 'nb.test', 'HTTP_USER_AGENT' => $userAgent,
        ];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->app->handle(new Request(query: self::query($path), post: $post, server: $server));
    }

    /** @return array<string, mixed> */
    private static function query(string $path): array
    {
        parse_str((string) parse_url($path, PHP_URL_QUERY), $query);

        /** @var array<string, mixed> $query */
        return $query;
    }

    protected function createUser(string $email, string $role = 'user', ?string $totpSecret = null): int
    {
        $c = $this->app->container();

        return $c->get(UserRepository::class)->create([
            'email' => $email,
            'password_hash' => $c->get(PasswordHasher::class)->hash('Soleil-Couchant-2026'),
            'name' => 'Test ' . $role,
            'role' => $role,
            'plan_id' => $role === 'user' ? $c->get(\App\Repositories\PlanRepository::class)->defaultPlanId() : null,
            'status' => 'active',
            'email_verified' => true,
            'totp_secret_enc' => $totpSecret !== null ? $c->get(Crypto::class)->encrypt($totpSecret, 'totp_secret') : null,
        ]);
    }

    protected function login(string $email): void
    {
        $response = $this->send('POST', '/login', ['email' => $email, 'password' => 'Soleil-Couchant-2026']);
        self::assertSame('/console', $response->header('location'), $response->body());
    }

    protected function loginSuperadmin(): int
    {
        $secret = Totp::generateSecret();
        $email = 'root-' . bin2hex(random_bytes(3)) . '@example.com';
        $id = $this->createUser($email, 'superadmin', $secret);
        $this->send('POST', '/login', ['email' => $email, 'password' => 'Soleil-Couchant-2026']);
        self::assertSame('/console', $this->send('POST', '/login/2fa', ['code' => (new Totp())->code($secret)])->header('location'));

        return $id;
    }

    /**
     * Extrait une correspondance obligatoire (échec du test sinon).
     */
    protected static function extract(string $pattern, string $subject, int $group = 0): string
    {
        if (preg_match($pattern, $subject, $m) !== 1 || !isset($m[$group])) {
            self::fail('Motif introuvable : ' . $pattern);
        }

        return $m[$group];
    }

    /** @return array{to: string, subject: string, html: string} */
    protected function lastMail(): array
    {
        $files = glob($this->mailDir . '/*.mail.php') ?: [];
        sort($files);
        $last = array_pop($files);
        if ($last === null) {
            self::fail('Aucun e-mail envoyé.');
        }
        /** @var array{to: string, subject: string, html: string} $mail */
        $mail = json_decode(explode("\n", (string) file_get_contents($last), 2)[1], true);

        return $mail;
    }
}
