<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\RequestHandlerInterface;
use App\Core\Http\Response;
use App\Core\Security\CspNonce;

/**
 * En-têtes de sécurité appliqués à toutes les réponses : CSP stricte avec nonce, HSTS,
 * nosniff, anti-framing, Referrer-Policy, Permissions-Policy.
 */
final class SecurityHeaders implements MiddlewareInterface
{
    public function __construct(
        private readonly CspNonce $nonce,
        private readonly Config $config,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $response = $next->handle($request);
        self::apply($response, $request, $this->nonce->value(), $this->config->int('security.hsts_max_age', 31536000));

        return $response;
    }

    public static function apply(Response $response, Request $request, string $nonce, int $hstsMaxAge): void
    {
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('X-Frame-Options', 'DENY');
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        $response->setHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->setHeader('X-Request-Id', $request->requestId());
        $response->removeHeader('X-Powered-By');

        if (!$response->hasHeader('Content-Security-Policy')) {
            $response->setHeader('Content-Security-Policy', $request->isApi()
                ? "default-src 'none'; frame-ancestors 'none'; base-uri 'none'"
                : self::webPolicy($nonce, $request->isSecure()));
        }
        if ($request->isSecure() && $hstsMaxAge > 0) {
            $response->setHeader('Strict-Transport-Security', 'max-age=' . $hstsMaxAge . '; includeSubDomains');
        }
        if (!$request->isApi() && !$response->hasHeader('Cache-Control')) {
            $response->setHeader('Cache-Control', 'no-store, private');
        }
    }

    public static function webPolicy(string $nonce, bool $secure): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-" . $nonce . "'",
            "style-src 'self' 'nonce-" . $nonce . "'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ];
        if ($secure) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
