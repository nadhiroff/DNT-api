<?php

declare(strict_types=1);

namespace App\Core\Http;

use JsonException;

/**
 * Requête HTTP entrante. Immuable pour les données client ; seuls les attributs
 * (paramètres de route, utilisateur courant…) sont modifiables par les middlewares.
 */
final class Request
{
    public const int JSON_MAX_DEPTH = 32;

    private string $method;
    private string $path;

    /** Préfixe d'installation (« /DNT-api » en sous-dossier, « » à la racine du domaine). */
    private string $basePath = '';

    /** Vrai si l'URL contient explicitement le script (« /DNT-api/index.php/login »). */
    private bool $viaScript = false;

    /** @var array<string, string> */
    private array $headers = [];

    /** @var array<string, mixed> */
    private array $attributes = [];

    private ?string $rawBody;

    /** @var array<mixed>|null */
    private ?array $jsonCache = null;

    /** @var list<string> */
    private array $trustedProxies = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     * @param array<string, string> $cookies
     * @param array<string, mixed> $files
     */
    public function __construct(
        private readonly array $query = [],
        private readonly array $post = [],
        private readonly array $server = [],
        private readonly array $cookies = [],
        private readonly array $files = [],
        ?string $rawBody = null,
    ) {
        $this->rawBody = $rawBody;
        $method = strtoupper(is_string($server['REQUEST_METHOD'] ?? null) ? $server['REQUEST_METHOD'] : 'GET');
        $this->method = preg_match('/^[A-Z]{1,10}$/', $method) === 1 ? $method : 'GET';
        $this->resolvePath(
            is_string($server['REQUEST_URI'] ?? null) ? $server['REQUEST_URI'] : '/',
            is_string($server['SCRIPT_NAME'] ?? null) ? $server['SCRIPT_NAME'] : '',
        );

        foreach ($server as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            if (str_starts_with($key, 'HTTP_')) {
                $this->headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            } elseif ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
                $this->headers[strtolower(str_replace('_', '-', $key))] = $value;
            }
        }
    }

    public static function fromGlobals(): self
    {
        /** @var array<string, string> $cookies */
        $cookies = array_filter($_COOKIE, 'is_string');

        // Le corps brut est lu à la demande (rawBody()) pour respecter les limites de taille.
        return new self($_GET, $_POST, $_SERVER, $cookies, $_FILES, null);
    }

    /**
     * Déduit le chemin à partir de l'URI : supprime la query string, décode, refuse les octets nuls
     * et normalise les barres obliques. Le résultat commence toujours par « / ».
     */
    public static function normalizePath(string $uri): string
    {
        // Pas de parse_url() : « //hote/x » y serait interprété comme un nom d'hôte.
        $path = explode('#', explode('?', $uri, 2)[0], 2)[0];
        if ($path === '') {
            return '/';
        }
        $path = rawurldecode($path);
        if (str_contains($path, "\0") || !mb_check_encoding($path, 'UTF-8')) {
            return '/__invalid__';
        }
        $path = '/' . trim((string) preg_replace('#/+#', '/', $path), '/');

        return $path;
    }

    /**
     * Sépare le préfixe d'installation du chemin applicatif. Cas gérés :
     *  - racine du domaine ou serveur intégré : « /login » ;
     *  - sous-dossier via index.php racine : « /DNT-api/login » (SCRIPT_NAME = /DNT-api/index.php) ;
     *  - sous-dossier réécrit vers public/ : « /DNT-api/login » (SCRIPT_NAME = /DNT-api/public/index.php) ;
     *  - sans réécriture d'URL : « /DNT-api/index.php/login ».
     */
    private function resolvePath(string $uri, string $scriptName): void
    {
        $uriPath = explode('#', explode('?', $uri, 2)[0], 2)[0];
        $rest = $uriPath;
        if (str_ends_with($scriptName, '.php') && str_starts_with($scriptName, '/')) {
            $dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
            if ($uriPath === $scriptName || str_starts_with($uriPath, $scriptName . '/')) {
                $this->viaScript = true;
                $this->basePath = $dir;
                $rest = substr($uriPath, strlen($scriptName));
            } else {
                if ($dir !== '' && !self::isUnder($uriPath, $dir) && str_ends_with($dir, '/public')) {
                    $dir = substr($dir, 0, -strlen('/public'));
                }
                if ($dir !== '' && self::isUnder($uriPath, $dir)) {
                    $this->basePath = $dir;
                    $rest = substr($uriPath, strlen($dir));
                }
            }
        }
        $this->path = self::normalizePath($rest === '' ? '/' : $rest);
    }

    private static function isUnder(string $path, string $prefix): bool
    {
        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function isViaScript(): bool
    {
        return $this->viaScript;
    }

    /** @param array<mixed> $proxies */
    public function withTrustedProxies(array $proxies): self
    {
        $this->trustedProxies = array_values(array_filter($proxies, 'is_string'));

        return $this;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function isSafeMethod(): bool
    {
        return in_array($this->method, ['GET', 'HEAD', 'OPTIONS'], true);
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function allQuery(): array
    {
        return $this->query;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if ($this->isJson()) {
            $json = $this->json();

            return array_key_exists($key, $json) ? $json[$key] : $default;
        }

        return $this->post[$key] ?? $default;
    }

    /**
     * Récupère un champ de formulaire sous forme de chaîne (les tableaux sont ignorés).
     */
    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->isJson()) {
            /** @var array<string, mixed> $json */
            $json = $this->json();

            return $json;
        }

        return $this->post;
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        $all = $this->all();

        return array_intersect_key($all, array_flip($keys));
    }

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    /** @return array<string, mixed> */
    public function files(): array
    {
        return $this->files;
    }

    public function server(string $key): mixed
    {
        return $this->server[$key] ?? null;
    }

    public function isJson(): bool
    {
        $type = $this->header('content-type', '') ?? '';

        return str_starts_with(strtolower($type), 'application/json')
            || (str_starts_with(strtolower($type), 'application/') && str_contains(strtolower($type), '+json'));
    }

    public function isApi(): bool
    {
        return $this->path === '/api' || str_starts_with($this->path, '/api/') || $this->path === '/health';
    }

    public function wantsJson(): bool
    {
        if ($this->isApi()) {
            return true;
        }
        $accept = strtolower($this->header('accept', '') ?? '');

        return str_contains($accept, 'application/json') || $this->header('x-requested-with') === 'XMLHttpRequest';
    }

    public function contentLength(): ?int
    {
        $length = $this->header('content-length');

        return $length !== null && ctype_digit($length) ? (int) $length : null;
    }

    /**
     * Lit le corps brut en refusant tout dépassement de $maxBytes (413).
     */
    public function rawBody(int $maxBytes = 1_048_576): string
    {
        if ($this->rawBody !== null) {
            if (strlen($this->rawBody) > $maxBytes) {
                throw new HttpException(413, 'payload_too_large');
            }

            return $this->rawBody;
        }

        $declared = $this->contentLength();
        if ($declared !== null && $declared > $maxBytes) {
            throw new HttpException(413, 'payload_too_large');
        }

        $stream = fopen('php://input', 'rb');
        if ($stream === false) {
            return $this->rawBody = '';
        }
        $body = stream_get_contents($stream, $maxBytes + 1);
        fclose($stream);
        $body = $body === false ? '' : $body;
        if (strlen($body) > $maxBytes) {
            throw new HttpException(413, 'payload_too_large');
        }

        return $this->rawBody = $body;
    }

    /**
     * Décode le corps JSON (profondeur max 32). Un JSON invalide produit une erreur 400.
     *
     * @return array<mixed>
     */
    public function json(int $maxBytes = 1_048_576): array
    {
        if ($this->jsonCache !== null) {
            return $this->jsonCache;
        }
        $raw = $this->rawBody($maxBytes);
        if (trim($raw) === '') {
            return $this->jsonCache = [];
        }
        try {
            $decoded = json_decode($raw, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            throw new HttpException(400, 'invalid_json', 'Le corps de la requête n\'est pas un JSON valide (profondeur maximale ' . self::JSON_MAX_DEPTH . ').');
        }
        if (!is_array($decoded)) {
            throw new HttpException(400, 'invalid_json', 'Le corps JSON doit être un objet ou un tableau.');
        }

        return $this->jsonCache = $decoded;
    }

    /**
     * Adresse IP du client. X-Forwarded-For n'est pris en compte que si REMOTE_ADDR
     * appartient à un proxy de confiance ; on prend alors la première IP non fiable en partant de la droite.
     */
    public function ip(): string
    {
        $remote = is_string($this->server['REMOTE_ADDR'] ?? null) ? $this->server['REMOTE_ADDR'] : '0.0.0.0';
        if ($this->trustedProxies === [] || !IpUtils::inAny($remote, $this->trustedProxies)) {
            return $remote;
        }
        $forwarded = $this->header('x-forwarded-for');
        if ($forwarded === null) {
            return $remote;
        }
        $chain = array_reverse(array_map('trim', explode(',', $forwarded)));
        foreach ($chain as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {
                break;
            }
            if (!IpUtils::inAny($candidate, $this->trustedProxies)) {
                return $candidate;
            }
        }

        return $remote;
    }

    public function isSecure(): bool
    {
        $https = $this->server['HTTPS'] ?? null;
        if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
            return true;
        }
        $remote = is_string($this->server['REMOTE_ADDR'] ?? null) ? $this->server['REMOTE_ADDR'] : '';
        if ($this->trustedProxies !== [] && IpUtils::inAny($remote, $this->trustedProxies)) {
            return strtolower($this->header('x-forwarded-proto', '') ?? '') === 'https';
        }

        return false;
    }

    public function userAgent(): string
    {
        return mb_substr($this->header('user-agent', '') ?? '', 0, 255);
    }

    public function referer(): ?string
    {
        return $this->header('referer');
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function route(string $param): ?string
    {
        $params = $this->attributes['route_params'] ?? [];

        return is_array($params) && is_string($params[$param] ?? null) ? $params[$param] : null;
    }

    public function requestId(): string
    {
        $id = $this->attributes['request_id'] ?? null;
        if (!is_string($id)) {
            $id = bin2hex(random_bytes(8));
            $this->attributes['request_id'] = $id;
        }

        return $id;
    }
}
