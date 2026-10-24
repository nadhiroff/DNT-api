<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Application;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Security\Csrf;
use App\Core\Security\Totp;
use App\Core\Session\Session;
use App\Repositories\UserRepository;
use App\Services\Auth\TwoFactorService;
use App\Services\Mail\MailTransport;

/**
 * Parcours complets via le noyau HTTP : inscription, vérification, connexion, anti-brute-force,
 * 2FA, mot de passe oublié, déconnexion. Un « navigateur » = une session en mémoire partagée.
 */
final class AuthFlowTest extends DatabaseTestCase
{
    private Application $app;

    private string $mailDir;

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['MAIL_DRIVER'] = 'log';
        $_ENV['QUEUE_DRIVER'] = 'sync';
        $this->app = self::app();
        $this->mailDir = sys_get_temp_dir() . '/nb-mail-' . bin2hex(random_bytes(4));
        $this->app->container()->instance(MailTransport::class, new MailTransport($this->app->config(), $this->mailDir));
        $this->newBrowser();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->mailDir . '/*') ?: []);
        if (is_dir($this->mailDir)) {
            rmdir($this->mailDir);
        }
        unset($_ENV['MAIL_DRIVER'], $_ENV['QUEUE_DRIVER']);
    }

    private function newBrowser(): void
    {
        $session = $this->app->container()->get(Session::class);
        $session->startInMemory();
    }

    /**
     * @param array<string, string> $post
     */
    private function send(string $method, string $path, array $post = [], bool $withCsrf = true): Response
    {
        if ($method !== 'GET' && $withCsrf) {
            $post['_csrf'] = $this->app->container()->get(Csrf::class)->token();
        }

        return $this->app->handle(new Request(post: $post, server: [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $path,
            'SCRIPT_NAME' => '/index.php',
            'REMOTE_ADDR' => '198.51.100.' . random_int(1, 200),
            'HTTP_HOST' => 'nb.test',
        ]));
    }

    private static function assertRedirect(Response $response, string $path): void
    {
        self::assertSame(303, $response->status(), $response->body());
        self::assertSame($path, $response->header('location'));
    }

    /** @return list<array{to: string, subject: string, html: string}> */
    private function mails(): array
    {
        $files = glob($this->mailDir . '/*.mail.php') ?: [];
        sort($files);

        return array_map(static function (string $file): array {
            /** @var array{to: string, subject: string, html: string} $mail */
            $mail = json_decode(explode("\n", (string) file_get_contents($file), 2)[1], true);

            return $mail;
        }, $files);
    }

    /** @return array{to: string, subject: string, html: string} */
    private function lastMail(): array
    {
        $mails = $this->mails();
        $last = array_pop($mails);
        if ($last === null) {
            self::fail('Aucun e-mail envoyé.');
        }

        return $last;
    }

    private function lastLink(string $pattern): string
    {
        self::assertSame(1, preg_match('#' . $pattern . '/([a-f0-9]{64})#', $this->lastMail()['html'], $m));

        return $m[1];
    }

    private function register(string $email, string $password = 'Soleil-Couchant-2026'): Response
    {
        return $this->send('POST', '/register', [
            'name' => 'Awa Diop', 'email' => $email, 'password' => $password, 'password_confirmation' => $password, 'terms' => '1',
        ]);
    }

    public function testPublicPagesRender(): void
    {
        foreach (['/', '/login', '/register', '/forgot-password', '/mentions-legales', '/confidentialite', '/conditions'] as $path) {
            $response = $this->send('GET', $path);
            self::assertSame(200, $response->status(), $path);
            self::assertStringContainsString('</html>', $response->body(), $path);
        }
        $home = $this->send('GET', '/')->body();
        self::assertStringContainsString('href="/register"', $home);
        self::assertStringContainsString('href="/login"', $home);
        self::assertStringContainsString('<footer', $home);
        self::assertStringContainsString('Gratuit', $home, 'plan lu en base');
    }

    public function testRegistrationVerificationAndLogin(): void
    {
        self::assertRedirect($this->register('awa@example.com'), '/register/done');
        self::assertStringContainsString('awa@example.com', $this->send('GET', '/register/done')->body());
        $user = $this->app->container()->get(UserRepository::class)->findByEmail('awa@example.com');
        self::assertNotNull($user);
        self::assertNull($user['email_verified_at']);
        self::assertSame('user', $user['role']);
        self::assertNotNull($user['plan_id']);

        // Connexion possible, mais console bloquée tant que l'adresse n'est pas confirmée.
        self::assertRedirect($this->send('POST', '/login', ['email' => 'awa@example.com', 'password' => 'Soleil-Couchant-2026']), '/console');
        self::assertRedirect($this->send('GET', '/console'), '/email/verify');

        $token = $this->lastLink('/email/verify');
        self::assertRedirect($this->send('GET', '/email/verify/' . $token), '/console');
        self::assertSame(410, $this->send('GET', '/email/verify/' . $token)->status(), 'lien à usage unique');

        $console = $this->send('GET', '/console');
        self::assertSame(200, $console->status());
        self::assertMatchesRegularExpression('/(Bonjour|Bonsoir) Awa/', $console->body());
        self::assertRedirect($this->send('GET', '/login'), '/console');

        self::assertRedirect($this->send('POST', '/logout'), '/login');
        self::assertRedirect($this->send('GET', '/console'), '/login');
    }

    public function testDuplicateRegistrationLooksIdenticalAndNotifiesOwner(): void
    {
        $this->register('dup@example.com');
        self::assertRedirect($this->register('dup@example.com'), '/register/done');
        self::assertSame(1, (int) self::db()->scalar('SELECT COUNT(*) FROM users WHERE email = ?', ['dup@example.com']));
        self::assertSame('Tentative d\'inscription avec votre adresse', $this->lastMail()['subject']);
    }

    public function testRegistrationValidation(): void
    {
        $response = $this->send('POST', '/register', ['name' => 'X', 'email' => 'pas-un-email', 'password' => 'azertyuiop', 'password_confirmation' => 'autre']);
        self::assertSame(303, $response->status());
        $page = $this->send('GET', '/register')->body();
        self::assertStringContainsString('aria-invalid="true"', $page);
        self::assertStringContainsString('adresse e-mail', $page);
        self::assertStringNotContainsString('azertyuiop', $page, 'mot de passe jamais réaffiché');
        self::assertRedirect($this->send('POST', '/register', ['name' => 'Awa', 'email' => 'weak@example.com', 'password' => 'azertyuiop', 'password_confirmation' => 'azertyuiop', 'terms' => '1']), '/register');
        self::assertStringContainsString('plus utilisés', $this->send('GET', '/register')->body());
    }

    public function testWrongPasswordMessageIsGenericAndThrottled(): void
    {
        $this->register('brute@example.com');
        $unknown = $this->send('POST', '/login', ['email' => 'inconnu@example.com', 'password' => 'whatever-123']);
        self::assertSame(303, $unknown->status());
        $message = 'Adresse e-mail ou mot de passe incorrect.';
        self::assertStringContainsString($message, $this->send('GET', '/login')->body());

        for ($i = 0; $i < 5; ++$i) {
            $this->send('POST', '/login', ['email' => 'brute@example.com', 'password' => 'mauvais-' . $i]);
            $this->send('GET', '/login');
        }
        $this->send('POST', '/login', ['email' => 'brute@example.com', 'password' => 'Soleil-Couchant-2026']);
        self::assertStringContainsString('Trop de tentatives', $this->send('GET', '/login')->body(), 'blocage même avec le bon mot de passe');
        self::assertRedirect($this->send('GET', '/console'), '/login');
    }

    public function testTwoFactorLogin(): void
    {
        $this->register('totp@example.com');
        $users = $this->app->container()->get(UserRepository::class);
        $user = $users->findByEmail('totp@example.com');
        self::assertNotNull($user);
        $users->markEmailVerified($user['id']);

        $session = $this->app->container()->get(Session::class);
        $twoFactor = $this->app->container()->get(TwoFactorService::class);
        $secret = $twoFactor->pendingSecret($session);
        $totp = new Totp();
        $codes = $twoFactor->confirmSetup($user['id'], $session, $totp->code($secret, time() - 30));
        self::assertIsArray($codes);

        $this->newBrowser();
        self::assertRedirect($this->send('POST', '/login', ['email' => 'totp@example.com', 'password' => 'Soleil-Couchant-2026']), '/login/2fa');
        self::assertRedirect($this->send('GET', '/console'), '/login');
        self::assertRedirect($this->send('POST', '/login/2fa', ['code' => '000000']), '/login/2fa');
        self::assertStringContainsString('Code invalide', $this->send('GET', '/login/2fa')->body());
        self::assertRedirect($this->send('POST', '/login/2fa', ['code' => $totp->code($secret)]), '/console');
        self::assertSame(200, $this->send('GET', '/console')->status());

        // Code de secours : usage unique.
        $this->send('POST', '/logout');
        $this->send('POST', '/login', ['email' => 'totp@example.com', 'password' => 'Soleil-Couchant-2026']);
        self::assertRedirect($this->send('POST', '/login/2fa', ['code' => strtolower($codes[0])]), '/console');
        $this->send('POST', '/logout');
        $this->send('POST', '/login', ['email' => 'totp@example.com', 'password' => 'Soleil-Couchant-2026']);
        self::assertRedirect($this->send('POST', '/login/2fa', ['code' => $codes[0]]), '/login/2fa');
    }

    public function testPasswordReset(): void
    {
        $this->register('reset@example.com');
        self::assertRedirect($this->send('POST', '/forgot-password', ['email' => 'reset@example.com']), '/forgot-password');
        self::assertRedirect($this->send('POST', '/forgot-password', ['email' => 'nobody@example.com']), '/forgot-password');
        $token = $this->lastLink('/reset-password');
        self::assertSame('Réinitialisation de votre mot de passe', $this->lastMail()['subject']);

        $form = $this->send('GET', '/reset-password/' . $token);
        self::assertSame(200, $form->status());
        // Mot de passe refusé : le lien reste utilisable.
        $this->send('POST', '/reset-password/' . $token, ['password' => 'azertyuiop', 'password_confirmation' => 'azertyuiop']);
        $stillValid = $this->send('GET', '/reset-password/' . $token);
        self::assertSame(200, $stillValid->status());

        self::assertRedirect($this->send('POST', '/reset-password/' . $token, ['password' => 'Lune-Rousse-Marée-77', 'password_confirmation' => 'Lune-Rousse-Marée-77']), '/login');
        self::assertSame(410, $this->send('GET', '/reset-password/' . $token)->status());

        $this->send('POST', '/login', ['email' => 'reset@example.com', 'password' => 'Soleil-Couchant-2026']);
        self::assertStringContainsString('incorrect', $this->send('GET', '/login')->body());
        self::assertRedirect($this->send('POST', '/login', ['email' => 'reset@example.com', 'password' => 'Lune-Rousse-Marée-77']), '/console');
        self::assertSame(200, $this->send('GET', '/console')->status(), 'lien de réinitialisation = adresse vérifiée');
    }

    public function testSuperadminMustEnrollTwoFactor(): void
    {
        $this->register('boss@example.com');
        self::db()->execute("UPDATE users SET role = 'superadmin', email_verified_at = UTC_TIMESTAMP() WHERE email = ?", ['boss@example.com']);
        $this->send('POST', '/login', ['email' => 'boss@example.com', 'password' => 'Soleil-Couchant-2026']);
        self::assertRedirect($this->send('GET', '/console'), '/account/2fa');
        self::assertRedirect($this->send('GET', '/account'), '/account/2fa');
        $setup = $this->send('GET', '/account/2fa');
        self::assertSame(200, $setup->status());
        self::assertStringContainsString('data-qr="otpauth://totp/', $setup->body());
    }

    public function testCsrfIsEnforced(): void
    {
        self::assertSame(403, $this->send('POST', '/login', ['email' => 'a@b.co', 'password' => 'x'], false)->status());
    }

    public function testAccountPageAndSessionRevocationIsScopedToOwner(): void
    {
        $this->register('acc@example.com');
        self::db()->execute('UPDATE users SET email_verified_at = UTC_TIMESTAMP() WHERE email = ?', ['acc@example.com']);
        $this->send('POST', '/login', ['email' => 'acc@example.com', 'password' => 'Soleil-Couchant-2026']);
        $page = $this->send('GET', '/account');
        self::assertSame(200, $page->status());
        self::assertStringContainsString('Sessions actives', $page->body());

        // Session d'un autre utilisateur : la révocation ne doit rien supprimer (IDOR).
        $otherId = hash('sha256', 'autre-session');
        self::db()->execute('INSERT INTO sessions (id, user_id, payload, last_activity, created_at) VALUES (?, NULL, ?, ?, ?)', [$otherId, '', time(), time()]);
        self::assertRedirect($this->send('POST', '/account/sessions/' . $otherId . '/revoke'), '/account');
        self::assertSame(1, (int) self::db()->scalar('SELECT COUNT(*) FROM sessions WHERE id = ?', [$otherId]));

        self::assertRedirect($this->send('POST', '/account/profile', ['name' => 'Awa D.']), '/account');
        self::assertSame('Awa D.', self::db()->scalar('SELECT name FROM users WHERE email = ?', ['acc@example.com']));

        self::assertRedirect($this->send('POST', '/account/delete', ['delete_password' => 'Soleil-Couchant-2026', 'delete_confirm' => 'supprimer']), '/account');
        self::assertRedirect($this->send('POST', '/account/delete', ['delete_password' => 'Soleil-Couchant-2026', 'delete_confirm' => 'SUPPRIMER']), '/');
        self::assertNull(self::db()->scalar('SELECT 1 FROM users WHERE email = ?', ['acc@example.com']));
    }
}
