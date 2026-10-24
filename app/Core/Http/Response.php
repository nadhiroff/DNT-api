<?php

declare(strict_types=1);

namespace App\Core\Http;

/**
 * Réponse HTTP. Les en-têtes sont normalisés en minuscules pour éviter les doublons.
 */
class Response
{
    /** @var array<string, string> */
    private array $headers = [];

    /** @var list<string> */
    private array $cookies = [];

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        protected string $body = '',
        protected int $status = 200,
        array $headers = [],
    ) {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
    }

    /**
     * @param array<string, string> $headers
     */
    public static function html(string $html, int $status = 200, array $headers = []): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=UTF-8'] + $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $body = $status === 204 ? '' : json_encode(
            $data,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );

        return new self($body, $status, ['Content-Type' => 'application/json; charset=UTF-8'] + $headers);
    }

    /**
     * Erreur au format unique de l'API : {"error":{"code":"…","message":"…"}}.
     *
     * @param array<string, mixed>  $details
     * @param array<string, string> $headers
     */
    public static function jsonError(int $status, string $code, string $message, array $details = [], array $headers = []): self
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== []) {
            $error['details'] = $details;
        }

        return self::json(['error' => $error], $status, $headers);
    }

    /**
     * Redirection interne uniquement : toute URL absolue ou « //hote » est remplacée par « / »
     * afin d'empêcher les redirections ouvertes.
     */
    public static function redirect(string $path, int $status = 302): self
    {
        if (!self::isSafeRedirect($path)) {
            $path = '/';
        }

        return new self('', $status, ['Location' => $path]);
    }

    public static function isSafeRedirect(string $path): bool
    {
        return $path !== ''
            && $path[0] === '/'
            && !str_starts_with($path, '//')
            && !str_starts_with($path, '/\\')
            && preg_match('/[\x00-\x1F\x7F]/', $path) !== 1;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function setStatus(int $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function setHeader(string $name, string $value): static
    {
        if (preg_match('/[\r\n\0]/', $name . $value) === 1) {
            throw new \InvalidArgumentException('En-tête HTTP invalide.');
        }
        $this->headers[strtolower($name)] = $value;

        return $this;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    public function removeHeader(string $name): static
    {
        unset($this->headers[strtolower($name)]);

        return $this;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Ajoute un cookie avec des valeurs par défaut sûres (HttpOnly, SameSite=Lax).
     */
    public function withCookie(
        string $name,
        string $value,
        int $expires = 0,
        string $path = '/',
        bool $secure = true,
        bool $httpOnly = true,
        string $sameSite = 'Lax',
    ): static {
        if (preg_match('/^[A-Za-z0-9_\-]+$/', $name) !== 1 || !in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            throw new \InvalidArgumentException('Cookie invalide.');
        }
        $cookie = $name . '=' . rawurlencode($value) . '; Path=' . $path . '; SameSite=' . $sameSite;
        if ($expires !== 0) {
            $cookie .= '; Expires=' . gmdate('D, d M Y H:i:s', $expires) . ' GMT; Max-Age=' . max(0, $expires - time());
        }
        if ($secure) {
            $cookie .= '; Secure';
        }
        if ($httpOnly) {
            $cookie .= '; HttpOnly';
        }
        $this->cookies[] = $cookie;

        return $this;
    }

    /** @return list<string> */
    public function cookies(): array
    {
        return $this->cookies;
    }

    public function send(bool $omitBody = false): void
    {
        if (!headers_sent()) {
            header_remove('X-Powered-By');
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header(self::canonicalName($name) . ': ' . $value, true);
            }
            foreach ($this->cookies as $cookie) {
                header('Set-Cookie: ' . $cookie, false);
            }
        }
        if (!$omitBody) {
            $this->sendBody();
        }
    }

    protected function sendBody(): void
    {
        echo $this->body;
    }

    private static function canonicalName(string $name): string
    {
        return implode('-', array_map('ucfirst', explode('-', $name)));
    }
}
