<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\Console\EnvFile;
use App\Core\Http\Request;
use App\Core\Http\UrlGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UrlAndEnvTest extends TestCase
{
    /** @return iterable<string, array{string, string, string, string, bool}> */
    public static function installations(): iterable
    {
        // uri, SCRIPT_NAME, base attendue, chemin attendu, via le script
        yield 'racine du domaine' => ['/login?x=1', '/index.php', '', '/login', false];
        yield 'sous-dossier, index.php racine' => ['/DNT-api/', '/DNT-api/index.php', '/DNT-api', '/', false];
        yield 'sous-dossier sans réécriture' => ['/DNT-api/index.php/login', '/DNT-api/index.php', '/DNT-api', '/login', true];
        yield 'sous-dossier réécrit vers public' => ['/DNT-api/console/projets', '/DNT-api/public/index.php', '/DNT-api', '/console/projets', false];
        yield 'public appelé directement' => ['/DNT-api/public/login', '/DNT-api/public/index.php', '/DNT-api/public', '/login', false];
        yield 'index.php seul' => ['/DNT-api/index.php', '/DNT-api/index.php', '/DNT-api', '/', true];
        yield 'préfixe homonyme' => ['/DNT-apiX/login', '/DNT-api/index.php', '', '/DNT-apiX/login', false];
    }

    #[DataProvider('installations')]
    public function testBasePathDetection(string $uri, string $script, string $base, string $path, bool $viaScript): void
    {
        $request = new Request(server: ['REQUEST_URI' => $uri, 'SCRIPT_NAME' => $script]);
        self::assertSame($base, $request->basePath());
        self::assertSame($path, $request->path());
        self::assertSame($viaScript, $request->isViaScript());
    }

    /** @param array<string, string> $server */
    private static function urls(string $rewrite, array $server): UrlGenerator
    {
        $root = dirname(__DIR__, 2);
        $urls = new UrlGenerator(new Config(['app' => ['url' => 'https://nb.example/DNT-api', 'url_rewrite' => $rewrite]]), $root . '/public');
        $urls->setRequest(new Request(server: $server));

        return $urls;
    }

    public function testUrlsWithoutRewriteGoThroughIndexPhp(): void
    {
        $root = dirname(__DIR__, 2);
        $urls = self::urls('auto', ['REQUEST_URI' => '/DNT-api/', 'SCRIPT_NAME' => '/DNT-api/index.php', 'SCRIPT_FILENAME' => $root . '/index.php']);
        self::assertSame('/DNT-api/index.php/login', $urls->to('/login'));
        self::assertSame('/DNT-api/index.php', $urls->to('/'));
        self::assertSame('/DNT-api/public/assets/css/app.css', $urls->asset('css/app.css'));
        self::assertSame('https://nb.example/DNT-api/index.php/verify?t=a%20b', $urls->absolute('/verify', ['t' => 'a b']));
    }

    public function testUrlsWithRewriteDetectedFromHtaccess(): void
    {
        $root = dirname(__DIR__, 2);
        $urls = self::urls('auto', ['REQUEST_URI' => '/DNT-api/login', 'SCRIPT_NAME' => '/DNT-api/public/index.php', 'SCRIPT_FILENAME' => $root . '/public/index.php', 'REDIRECT_NOVABASE_REWRITE' => '1']);
        self::assertSame('/DNT-api/login', $urls->to('/login'));
        self::assertSame('/DNT-api/assets/css/app.css', $urls->asset('css/app.css'));
    }

    public function testRewriteCanBeForced(): void
    {
        $urls = self::urls('true', ['REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php']);
        self::assertSame('/', $urls->to('/'));
        self::assertSame('/login', $urls->to('/login'));
    }

    public function testExternalUrlsAreRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        self::urls('true', [])->to('//evil.example');
    }

    public function testEnvFileEditsPhpConfigWithoutExecutingIt(): void
    {
        $file = sys_get_temp_dir() . '/nb-env-' . bin2hex(random_bytes(4)) . '.php';
        copy(dirname(__DIR__, 2) . '/config/env.example.php', $file);
        $env = new EnvFile($file);
        $env->set('APP_KEY', "base64:a+b/c=='\\x");
        $env->set('EXTRA_VALUE', 'ok');
        self::assertSame("base64:a+b/c=='\\x", $env->get('APP_KEY'));
        self::assertSame('NovaBase', $env->get('APP_NAME'));
        self::assertSame('ok', $env->get('EXTRA_VALUE'));
        self::assertNull($env->get('MISSING'));
        $values = (static fn (): mixed => require $file)();
        self::assertIsArray($values);
        self::assertSame("base64:a+b/c=='\\x", $values['APP_KEY']);
        unlink($file);
    }

    public function testConfigFilesRefuseDirectExecution(): void
    {
        foreach (['config/env.example.php', 'config/app.php', 'config/database.php', 'config/security.php', 'config/mail.php', 'config/routes.php'] as $file) {
            $head = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $file, length: 600);
            self::assertStringContainsString("if (!defined('NOVABASE_ROOT'))", $head, $file);
        }
    }
}
