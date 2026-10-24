<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Application;
use App\Core\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Traverse le noyau HTTP complet (middlewares globaux, routeur, gestion d'erreurs) sans base de données.
 */
final class HttpKernelTest extends TestCase
{
    private static Application $app;

    public static function setUpBeforeClass(): void
    {
        // Base injoignable : vérifie que le noyau reste robuste et n'ouvre aucune connexion inutile.
        foreach (['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)), 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '1', 'LOG_LEVEL' => 'critical'] as $k => $v) {
            $_ENV[$k] = $v;
        }
        self::$app = Application::create(dirname(__DIR__, 2), false);
    }

    /** @param array<string, string> $server */
    private static function request(string $method, string $uri, array $server = []): Request
    {
        return new Request(server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'nb.test'] + $server);
    }

    public function testApi404IsJsonWithUniformErrorFormat(): void
    {
        $response = self::$app->handle(self::request('GET', '/api/v1/inconnu'));
        self::assertSame(404, $response->status());
        self::assertSame(['error' => ['code' => 'not_found', 'message' => 'Ressource introuvable.']], json_decode($response->body(), true));
        self::assertSame("default-src 'none'; frame-ancestors 'none'; base-uri 'none'", $response->header('content-security-policy'));
    }

    public function testWeb404IsHtmlWithStrictCsp(): void
    {
        $response = self::$app->handle(self::request('GET', '/page-inexistante'));
        self::assertSame(404, $response->status());
        self::assertStringContainsString('text/html', (string) $response->header('content-type'));
        self::assertStringContainsString('Cette page n&#039;existe pas', $response->body());
        $csp = (string) $response->header('content-security-policy');
        self::assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9_-]{24}'/", $csp);
        self::assertStringNotContainsString('unsafe-inline', $csp);
        self::assertStringNotContainsString('unsafe-eval', $csp);
    }

    public function testSecurityHeadersOnEveryResponse(): void
    {
        $response = self::$app->handle(self::request('GET', '/nope'));
        self::assertSame('nosniff', $response->header('x-content-type-options'));
        self::assertSame('DENY', $response->header('x-frame-options'));
        self::assertSame('strict-origin-when-cross-origin', $response->header('referrer-policy'));
        self::assertNotNull($response->header('permissions-policy'));
        self::assertNotNull($response->header('x-request-id'));
        self::assertNull($response->header('strict-transport-security'), 'HSTS uniquement en HTTPS');
    }

    public function testHstsOverHttps(): void
    {
        $response = self::$app->handle(self::request('GET', '/nope', ['HTTPS' => 'on']));
        self::assertSame('max-age=31536000; includeSubDomains', $response->header('strict-transport-security'));
        self::assertStringContainsString('upgrade-insecure-requests', (string) $response->header('content-security-policy'));
    }

    public function testMethodNotAllowed(): void
    {
        $response = self::$app->handle(self::request('DELETE', '/health'));
        self::assertSame(405, $response->status());
        self::assertSame('GET', $response->header('allow'));
    }

    public function testHealthReportsDatabaseFailureWithoutLeakingDetails(): void
    {
        $response = self::$app->handle(self::request('GET', '/health'));
        self::assertSame(503, $response->status());
        $body = json_decode($response->body(), true);
        self::assertIsArray($body);
        self::assertSame('fail', $body['status']);
        self::assertSame('fail', $body['checks']['database']['status']);
        self::assertStringNotContainsString('SQLSTATE', $response->body());
    }

    public function testInternalErrorsNeverExposeDetailsInProduction(): void
    {
        // La page d'accueil démarre une session en base : base injoignable → 500 générique.
        $response = self::$app->handle(self::request('GET', '/'));
        self::assertSame(500, $response->status());
        self::assertStringNotContainsString('PDOException', $response->body());
        self::assertStringNotContainsString('SQLSTATE', $response->body());
        self::assertStringNotContainsString('.php', $response->body());
    }
}
