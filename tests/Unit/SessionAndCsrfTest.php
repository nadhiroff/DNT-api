<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Core\Http\RequestHandlerInterface;
use App\Core\Http\Response;
use App\Core\Security\Csrf;
use App\Core\Session\Session;
use App\Middleware\Csrf as CsrfMiddleware;
use PHPUnit\Framework\TestCase;

final class SessionAndCsrfTest extends TestCase
{
    /** @param array<string, mixed> $data */
    private function session(array $data = [], int $lifetime = 7200): Session
    {
        $session = new Session(null, 'nb_session', $lifetime);
        $session->startInMemory($data);

        return $session;
    }

    public function testFlashLivesForOneRequest(): void
    {
        $session = $this->session();
        $session->flash('status', 'ok');
        self::assertSame('ok', $session->getFlash('status'));
    }

    public function testIdleExpiryWipesSession(): void
    {
        $session = $this->session(['_last_activity' => time() - 7201, '_uid' => 5]);
        self::assertTrue($session->wasExpired());
        self::assertNull($session->userId());
    }

    public function testActiveSessionIsKept(): void
    {
        $session = $this->session(['_last_activity' => time() - 60, '_uid' => 5]);
        self::assertFalse($session->wasExpired());
        self::assertSame(5, $session->userId());
    }

    public function testLoginAndInvalidate(): void
    {
        $session = $this->session();
        $session->login(9);
        self::assertSame(9, $session->userId());
        $session->set('x', 1);
        $session->invalidate();
        self::assertNull($session->userId());
        self::assertFalse($session->has('x'));
    }

    public function testUnstartedSessionThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        (new Session(null))->get('x');
    }

    public function testCsrfTokenValidation(): void
    {
        $csrf = new Csrf($this->session());
        $token = $csrf->token();
        self::assertSame(64, strlen($token));
        self::assertSame($token, $csrf->token());
        self::assertTrue($csrf->validate($token));
        self::assertFalse($csrf->validate('x' . substr($token, 1)));
        self::assertFalse($csrf->validate(null));
        self::assertFalse($csrf->validate(''));
        self::assertNotSame($token, $csrf->rotate());
    }

    public function testMiddlewareBlocksMissingToken(): void
    {
        $middleware = new CsrfMiddleware(new Csrf($this->session()));
        $request = new Request(server: ['REQUEST_METHOD' => 'POST', 'HTTP_HOST' => 'nb.test']);
        try {
            $middleware->process($request, self::okHandler());
            self::fail('403 attendu');
        } catch (HttpException $e) {
            self::assertSame(403, $e->status());
            self::assertSame('csrf_token_mismatch', $e->errorCode());
        }
    }

    public function testMiddlewareAcceptsFieldOrHeader(): void
    {
        $csrf = new Csrf($this->session());
        $middleware = new CsrfMiddleware($csrf);
        $byField = new Request(post: ['_csrf' => $csrf->token()], server: ['REQUEST_METHOD' => 'POST', 'HTTP_HOST' => 'nb.test']);
        self::assertSame(200, $middleware->process($byField, self::okHandler())->status());
        $byHeader = new Request(server: ['REQUEST_METHOD' => 'DELETE', 'HTTP_HOST' => 'nb.test', 'HTTP_X_CSRF_TOKEN' => $csrf->token(), 'HTTP_ORIGIN' => 'https://nb.test']);
        self::assertSame(200, $middleware->process($byHeader, self::okHandler())->status());
    }

    public function testMiddlewareRejectsForeignOrigin(): void
    {
        $csrf = new Csrf($this->session());
        $request = new Request(post: ['_csrf' => $csrf->token()], server: ['REQUEST_METHOD' => 'POST', 'HTTP_HOST' => 'nb.test', 'HTTP_ORIGIN' => 'https://evil.test']);
        $this->expectException(HttpException::class);
        (new CsrfMiddleware($csrf))->process($request, self::okHandler());
    }

    public function testSafeMethodsSkipCheck(): void
    {
        $middleware = new CsrfMiddleware(new Csrf($this->session()));
        self::assertSame(200, $middleware->process(new Request(server: ['REQUEST_METHOD' => 'GET']), self::okHandler())->status());
    }

    private static function okHandler(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(Request $request): Response
            {
                return new Response('ok');
            }
        };
    }
}
