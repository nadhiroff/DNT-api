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
use App\Services\AvatarService;

/**
 * Espace superadmin : contrôle d'accès, témoignages (brouillon / publication / photo), réglages.
 */
final class AdminTest extends DatabaseTestCase
{
    private Application $app;

    private string $avatarDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = self::app();
        $this->avatarDir = sys_get_temp_dir() . '/nb-avatars-' . bin2hex(random_bytes(4));
        $this->app->container()->instance(AvatarService::class, new AvatarService($this->avatarDir));
        $this->app->container()->get(Session::class)->startInMemory();
        self::db()->execute('DELETE FROM testimonials');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->avatarDir . '/*') ?: []);
        if (is_dir($this->avatarDir)) {
            rmdir($this->avatarDir);
        }
        $_FILES = [];
    }

    /**
     * @param array<string, string>               $post
     * @param array<string, array<string, mixed>> $files
     */
    private function send(string $method, string $path, array $post = [], array $files = []): Response
    {
        if ($method !== 'GET') {
            $post['_csrf'] = $this->app->container()->get(Csrf::class)->token();
        }

        return $this->app->handle(new Request(post: $post, server: [
            'REQUEST_METHOD' => $method, 'REQUEST_URI' => $path, 'SCRIPT_NAME' => '/index.php',
            'REMOTE_ADDR' => '198.51.100.' . random_int(1, 200), 'HTTP_HOST' => 'nb.test',
        ], files: $files));
    }

    private function createUser(string $email, string $role, ?string $totpSecret = null): void
    {
        $users = $this->app->container()->get(UserRepository::class);
        $id = $users->create([
            'email' => $email, 'password_hash' => $this->app->container()->get(PasswordHasher::class)->hash('Soleil-Couchant-2026'),
            'name' => 'Test', 'role' => $role, 'plan_id' => null, 'status' => 'active', 'email_verified' => true,
            'totp_secret_enc' => $totpSecret !== null ? $this->app->container()->get(\App\Services\Crypto::class)->encrypt($totpSecret, 'totp_secret') : null,
        ]);
        self::assertGreaterThan(0, $id);
    }

    private function loginSuperadmin(): void
    {
        $secret = Totp::generateSecret();
        $this->createUser('root-' . bin2hex(random_bytes(3)) . '@example.com', 'superadmin', $secret);
        $email = (string) self::db()->scalar("SELECT email FROM users WHERE role = 'superadmin' ORDER BY id DESC LIMIT 1");
        $this->send('POST', '/login', ['email' => $email, 'password' => 'Soleil-Couchant-2026']);
        $response = $this->send('POST', '/login/2fa', ['code' => (new Totp())->code($secret)]);
        self::assertSame('/console', $response->header('location'));
    }

    public function testRegularUserCannotSeeAdmin(): void
    {
        $this->createUser('simple@example.com', 'user');
        $this->send('POST', '/login', ['email' => 'simple@example.com', 'password' => 'Soleil-Couchant-2026']);
        self::assertSame(200, $this->send('GET', '/console')->status());
        self::assertSame(404, $this->send('GET', '/admin')->status());
        self::assertSame(404, $this->send('GET', '/admin/testimonials')->status());
        self::assertSame(404, $this->send('POST', '/admin/testimonials', ['author_name' => 'X', 'handle' => 'x', 'platform' => 'x', 'content' => 'Contenu de test assez long'])->status());
        self::assertStringNotContainsString('Administration', $this->send('GET', '/console')->body());
    }

    public function testAnonymousIsRedirectedToLogin(): void
    {
        self::assertSame(303, $this->send('GET', '/admin')->status());
    }

    public function testTestimonialLifecycle(): void
    {
        $this->loginSuperadmin();
        self::assertStringContainsString('Administration', $this->send('GET', '/console')->body());
        self::assertSame(200, $this->send('GET', '/admin')->status());

        $created = $this->send('POST', '/admin/testimonials', [
            'author_name' => 'Awa Diop', 'handle' => 'awadiop', 'platform' => 'x',
            'profile_url' => 'https://x.com/awadiop', 'content' => "Super produit !\nJe recommande.", 'sort' => '5',
        ]);
        self::assertSame('/admin/testimonials', $created->header('location'));
        $row = self::db()->selectOne('SELECT id, handle, is_published FROM testimonials WHERE author_name = ?', ['Awa Diop']);
        self::assertNotNull($row);
        self::assertSame('@awadiop', $row['handle']);
        self::assertSame(0, (int) $row['is_published']);

        // Brouillon : visible en aperçu par le superadmin, invisible pour le public.
        self::assertStringContainsString('Aperçu superadmin', $this->send('GET', '/')->body());
        $this->send('POST', '/logout');
        self::assertStringNotContainsString('@awadiop', $this->send('GET', '/')->body());

        $this->loginSuperadmin();
        $this->send('POST', '/admin/testimonials/' . $row['id'] . '/publish');
        $this->send('POST', '/logout');
        $home = $this->send('GET', '/')->body();
        self::assertStringContainsString('@awadiop', $home);
        self::assertStringContainsString('href="https://x.com/awadiop"', $home);
        self::assertStringContainsString('target="_blank" rel="noopener noreferrer nofollow ugc"', $home);

        $this->loginSuperadmin();
        $this->send('POST', '/admin/testimonials/' . $row['id'] . '/delete');
        self::assertNull(self::db()->scalar('SELECT 1 FROM testimonials WHERE id = ?', [$row['id']]));
    }

    public function testValidationRejectsDangerousInput(): void
    {
        $this->loginSuperadmin();
        $response = $this->send('POST', '/admin/testimonials', [
            'author_name' => 'X', 'handle' => '<script>', 'platform' => 'myspace', 'profile_url' => 'javascript:alert(1)', 'content' => 'court',
        ]);
        self::assertSame(303, $response->status());
        self::assertSame(0, (int) self::db()->scalar('SELECT COUNT(*) FROM testimonials'));
        $form = $this->send('GET', '/admin/testimonials/new')->body();
        self::assertStringContainsString('aria-invalid="true"', $form);
        self::assertStringNotContainsString('<script>', $form);
    }

    public function testAvatarUploadIsReencodedAndServed(): void
    {
        $this->loginSuperadmin();
        $fake = tempnam(sys_get_temp_dir(), 'nb');
        file_put_contents($fake, "<?php echo 'pwned';");
        $this->send('POST', '/admin/testimonials', ['author_name' => 'Fake', 'handle' => 'fake', 'platform' => 'x', 'content' => 'Contenu de test assez long'], [
            'avatar' => ['name' => 'a.png', 'type' => 'image/png', 'tmp_name' => $fake, 'error' => UPLOAD_ERR_OK, 'size' => (int) filesize($fake)],
        ]);
        self::assertSame(0, (int) self::db()->scalar('SELECT COUNT(*) FROM testimonials'), 'faux PNG refusé');
        self::assertStringContainsString('Formats acceptés', $this->send('GET', '/admin/testimonials/new')->body());

        $image = imagecreatetruecolor(300, 200);
        self::assertNotFalse($image);
        $png = tempnam(sys_get_temp_dir(), 'nb');
        imagepng($image, $png);
        $this->send('POST', '/admin/testimonials', ['author_name' => 'Photo', 'handle' => 'photo', 'platform' => 'github', 'content' => 'Contenu de test assez long'], [
            'avatar' => ['name' => 'photo.png', 'type' => 'image/png', 'tmp_name' => $png, 'error' => UPLOAD_ERR_OK, 'size' => (int) filesize($png)],
        ]);
        $file = (string) self::db()->scalar('SELECT avatar_file FROM testimonials WHERE author_name = ?', ['Photo']);
        self::assertTrue(AvatarService::isValidName($file));
        $info = getimagesize($this->avatarDir . '/' . $file);
        self::assertIsArray($info);
        self::assertSame([160, 160], [$info[0], $info[1]]);

        $served = $this->send('GET', '/media/avatars/' . $file);
        self::assertSame(200, $served->status());
        self::assertContains($served->header('content-type'), ['image/webp', 'image/png']);
        self::assertSame(404, $this->send('GET', '/media/avatars/' . str_repeat('0', 32) . '.webp')->status());
        @unlink($fake);
        @unlink($png);
    }

    public function testCommunitySettings(): void
    {
        $this->loginSuperadmin();
        self::assertSame('/admin/community', $this->send('POST', '/admin/community', [
            'community_title' => 'Ils nous font confiance', 'community_subtitle' => 'Retours de nos utilisateurs.',
            'community_button_label' => 'Rejoindre le Discord', 'community_button_url' => 'https://discord.gg/novabase',
        ])->header('location'));
        $this->send('POST', '/admin/testimonials', ['author_name' => 'Pub', 'handle' => 'pub', 'platform' => 'linkedin', 'content' => 'Contenu de test assez long', 'is_published' => '1']);
        $this->send('POST', '/logout');
        $home = $this->send('GET', '/')->body();
        self::assertStringContainsString('Ils nous font confiance', $home);
        self::assertStringContainsString('href="https://discord.gg/novabase"', $home);

        $this->loginSuperadmin();
        $this->send('POST', '/admin/community', ['community_title' => 'Titre', 'community_button_url' => 'ftp://evil']);
        self::assertSame('https://discord.gg/novabase', self::db()->scalar("SELECT value FROM site_settings WHERE setting_key = 'community_button_url'"));
    }
}
