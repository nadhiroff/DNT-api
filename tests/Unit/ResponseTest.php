<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Http\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function redirects(): iterable
    {
        yield 'chemin interne' => ['/console?x=1', '/console?x=1'];
        yield 'url absolue' => ['https://evil.example', '/'];
        yield 'protocole relatif' => ['//evil.example', '/'];
        yield 'antislash' => ['/\\evil.example', '/'];
        yield 'injection crlf' => ["/a\r\nSet-Cookie: x=1", '/'];
        yield 'javascript' => ['javascript:alert(1)', '/'];
    }

    #[DataProvider('redirects')]
    public function testRedirectOnlyAllowsInternalPaths(string $target, string $expected): void
    {
        self::assertSame($expected, Response::redirect($target)->header('Location'));
    }

    public function testHeaderInjectionIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Response())->setHeader('X-Test', "a\r\nb");
    }

    public function testJsonErrorFormat(): void
    {
        $response = Response::jsonError(402, 'quota_exceeded', 'Quota dépassé.', ['limit' => 'db_storage']);
        self::assertSame(402, $response->status());
        self::assertSame('application/json; charset=UTF-8', $response->header('content-type'));
        self::assertSame(
            ['error' => ['code' => 'quota_exceeded', 'message' => 'Quota dépassé.', 'details' => ['limit' => 'db_storage']]],
            json_decode($response->body(), true),
        );
    }

    public function testCookieDefaultsAreSecure(): void
    {
        $cookie = (new Response())->withCookie('nb_test', 'a b')->cookies()[0];
        self::assertStringContainsString('nb_test=a%20b', $cookie);
        self::assertStringContainsString('HttpOnly', $cookie);
        self::assertStringContainsString('Secure', $cookie);
        self::assertStringContainsString('SameSite=Lax', $cookie);
    }
}
