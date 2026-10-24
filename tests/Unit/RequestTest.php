<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Http\HttpException;
use App\Core\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function paths(): iterable
    {
        yield 'racine' => ['/', '/'];
        yield 'query string' => ['/a/b?x=1', '/a/b'];
        yield 'slash final' => ['/a/b/', '/a/b'];
        yield 'doubles slashes' => ['//a///b', '/a/b'];
        yield 'encodage' => ['/caf%C3%A9', '/café'];
        yield 'octet nul' => ['/a%00b', '/__invalid__'];
        yield 'utf8 invalide' => ['/%FF', '/__invalid__'];
    }

    #[DataProvider('paths')]
    public function testNormalizePath(string $uri, string $expected): void
    {
        self::assertSame($expected, Request::normalizePath($uri));
    }

    public function testHeadersAreLowercased(): void
    {
        $request = new Request(server: ['HTTP_X_CUSTOM_HEADER' => 'v', 'CONTENT_TYPE' => 'application/json']);
        self::assertSame('v', $request->header('X-Custom-Header'));
        self::assertTrue($request->isJson());
    }

    public function testInvalidMethodFallsBackToGet(): void
    {
        self::assertSame('GET', (new Request(server: ['REQUEST_METHOD' => "PO\nST"]))->method());
    }

    public function testForwardedForIgnoredWithoutTrustedProxy(): void
    {
        $request = new Request(server: ['REMOTE_ADDR' => '203.0.113.5', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4']);
        self::assertSame('203.0.113.5', $request->ip());
    }

    public function testForwardedForFromTrustedProxyTakesRightmostUntrustedIp(): void
    {
        $request = (new Request(server: [
            'REMOTE_ADDR' => '10.0.0.2',
            'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 198.51.100.7, 10.0.0.1',
        ]))->withTrustedProxies(['10.0.0.0/8']);
        // 6.6.6.6 a pu être forgé par le client : on retient la première IP non fiable depuis la droite.
        self::assertSame('198.51.100.7', $request->ip());
    }

    public function testForwardedProtoOnlyTrustedFromProxy(): void
    {
        $direct = new Request(server: ['REMOTE_ADDR' => '203.0.113.5', 'HTTP_X_FORWARDED_PROTO' => 'https']);
        self::assertFalse($direct->isSecure());
        $proxied = (new Request(server: ['REMOTE_ADDR' => '10.0.0.2', 'HTTP_X_FORWARDED_PROTO' => 'https']))->withTrustedProxies(['10.0.0.0/8']);
        self::assertTrue($proxied->isSecure());
    }

    public function testJsonBodyIsDecoded(): void
    {
        $request = new Request(server: ['CONTENT_TYPE' => 'application/json'], rawBody: '{"name":"Ada","n":12345678901234567890123}');
        self::assertSame('Ada', $request->input('name'));
        // Les grands entiers restent des chaînes : aucune perte de précision.
        self::assertSame('12345678901234567890123', $request->input('n'));
    }

    public function testInvalidJsonIs400(): void
    {
        $request = new Request(server: ['CONTENT_TYPE' => 'application/json'], rawBody: '{bad');
        try {
            $request->json();
            self::fail('400 attendu');
        } catch (HttpException $e) {
            self::assertSame(400, $e->status());
            self::assertSame('invalid_json', $e->errorCode());
        }
    }

    public function testJsonDepthIsLimited(): void
    {
        $deep = str_repeat('[', 40) . str_repeat(']', 40);
        $request = new Request(server: ['CONTENT_TYPE' => 'application/json'], rawBody: $deep);
        try {
            $request->json();
            self::fail('400 attendu');
        } catch (HttpException $e) {
            self::assertSame('invalid_json', $e->errorCode());
        }
    }

    public function testBodySizeLimitIs413(): void
    {
        $request = new Request(server: ['CONTENT_TYPE' => 'application/json'], rawBody: str_repeat('a', 2048));
        try {
            $request->rawBody(1024);
            self::fail('413 attendu');
        } catch (HttpException $e) {
            self::assertSame(413, $e->status());
        }
    }

    public function testStringIgnoresArrays(): void
    {
        $request = new Request(post: ['email' => ['x'], 'name' => '  Ada ']);
        self::assertSame('', $request->string('email'));
        self::assertSame('Ada', $request->string('name'));
    }

    public function testApiDetection(): void
    {
        self::assertTrue((new Request(server: ['REQUEST_URI' => '/api/v1/x']))->wantsJson());
        self::assertFalse((new Request(server: ['REQUEST_URI' => '/apis']))->isApi());
    }
}
