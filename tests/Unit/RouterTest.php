<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Http\HttpException;
use App\Core\Http\Router;
use App\Middleware\Csrf;
use App\Middleware\StartSession;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->router->get('/', [self::class, 'home'], name: 'home');
        $this->router->get('/projects/{ref:[a-z0-9]{20}}', [self::class, 'show'], name: 'project');
        $this->router->post('/projects', [self::class, 'store']);
        $this->router->group('/admin', [StartSession::class], function (Router $r): void {
            $r->group('users', [Csrf::class], function (Router $r): void {
                $r->delete('/{id:\d+}', [self::class, 'destroy'], name: 'admin.users.delete');
            });
        });
    }

    public function testMatchesStaticRoute(): void
    {
        $match = $this->router->match('GET', '/');
        self::assertSame('home', $match['route']->name);
        self::assertSame([], $match['params']);
    }

    public function testExtractsConstrainedParameters(): void
    {
        $match = $this->router->match('GET', '/projects/abcdefghij0123456789');
        self::assertSame(['ref' => 'abcdefghij0123456789'], $match['params']);
    }

    public function testRejectsParameterNotMatchingConstraint(): void
    {
        $this->expectException(HttpException::class);
        $this->router->match('GET', '/projects/ABC');
    }

    public function testNotFoundIs404(): void
    {
        try {
            $this->router->match('GET', '/nope');
            self::fail('404 attendu');
        } catch (HttpException $e) {
            self::assertSame(404, $e->status());
        }
    }

    public function testWrongMethodIs405WithAllowHeader(): void
    {
        try {
            $this->router->match('PUT', '/projects');
            self::fail('405 attendu');
        } catch (HttpException $e) {
            self::assertSame(405, $e->status());
            self::assertSame(['Allow' => 'POST'], $e->headers());
        }
    }

    public function testHeadFallsBackToGet(): void
    {
        self::assertSame('home', $this->router->match('HEAD', '/')['route']->name);
    }

    public function testNestedGroupsComposePrefixAndMiddleware(): void
    {
        $match = $this->router->match('DELETE', '/admin/users/42');
        self::assertSame(['id' => '42'], $match['params']);
        self::assertSame([StartSession::class, Csrf::class], $match['route']->middleware);
    }

    public function testRegexMetacharactersInStaticSegmentsAreLiteral(): void
    {
        $router = new Router();
        $router->get('/file.json', [self::class, 'home']);
        $this->expectException(HttpException::class);
        $router->match('GET', '/fileXjson');
    }

    public function testUrlGeneration(): void
    {
        self::assertSame('/admin/users/7', $this->router->url('admin.users.delete', ['id' => 7]));
        $this->expectException(\InvalidArgumentException::class);
        $this->router->url('admin.users.delete');
    }

    public function testDuplicateRouteNameIsRejected(): void
    {
        $this->expectException(\LogicException::class);
        $this->router->get('/other', [self::class, 'home'], name: 'home');
    }
}
