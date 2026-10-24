<?php

declare(strict_types=1);

namespace App\Core\Http;

use Closure;

/**
 * Routeur minimaliste : routes statiques/dynamiques, groupes (préfixe + middlewares), routes nommées.
 */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** @var array<string, Route> */
    private array $named = [];

    private string $prefix = '';

    /** @var list<class-string<MiddlewareInterface>> */
    private array $groupMiddleware = [];

    /**
     * @param array{0: class-string, 1: string}       $handler
     * @param list<class-string<MiddlewareInterface>> $middleware
     */
    public function get(string $pattern, array $handler, array $middleware = [], ?string $name = null): Route
    {
        return $this->add(['GET'], $pattern, $handler, $middleware, $name);
    }

    /**
     * @param array{0: class-string, 1: string}       $handler
     * @param list<class-string<MiddlewareInterface>> $middleware
     */
    public function post(string $pattern, array $handler, array $middleware = [], ?string $name = null): Route
    {
        return $this->add(['POST'], $pattern, $handler, $middleware, $name);
    }

    /**
     * @param array{0: class-string, 1: string}       $handler
     * @param list<class-string<MiddlewareInterface>> $middleware
     */
    public function put(string $pattern, array $handler, array $middleware = [], ?string $name = null): Route
    {
        return $this->add(['PUT'], $pattern, $handler, $middleware, $name);
    }

    /**
     * @param array{0: class-string, 1: string}       $handler
     * @param list<class-string<MiddlewareInterface>> $middleware
     */
    public function patch(string $pattern, array $handler, array $middleware = [], ?string $name = null): Route
    {
        return $this->add(['PATCH'], $pattern, $handler, $middleware, $name);
    }

    /**
     * @param array{0: class-string, 1: string}       $handler
     * @param list<class-string<MiddlewareInterface>> $middleware
     */
    public function delete(string $pattern, array $handler, array $middleware = [], ?string $name = null): Route
    {
        return $this->add(['DELETE'], $pattern, $handler, $middleware, $name);
    }

    /**
     * @param list<string>                            $methods
     * @param array{0: class-string, 1: string}       $handler
     * @param list<class-string<MiddlewareInterface>> $middleware
     */
    public function add(array $methods, string $pattern, array $handler, array $middleware = [], ?string $name = null): Route
    {
        $full = $this->prefix . '/' . ltrim($pattern, '/');
        $full = $full === '/' ? '/' : rtrim($full, '/');
        $route = new Route(
            array_map('strtoupper', $methods),
            $full,
            $handler,
            [...$this->groupMiddleware, ...$middleware],
            $name,
        );
        $this->routes[] = $route;
        if ($name !== null) {
            if (isset($this->named[$name])) {
                throw new \LogicException('Nom de route déjà utilisé : ' . $name);
            }
            $this->named[$name] = $route;
        }

        return $route;
    }

    /**
     * @param list<class-string<MiddlewareInterface>> $middleware
     * @param Closure(Router): void                   $routes
     */
    public function group(string $prefix, array $middleware, Closure $routes): void
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;
        $this->prefix = rtrim($previousPrefix . '/' . trim($prefix, '/'), '/');
        $this->groupMiddleware = [...$previousMiddleware, ...$middleware];
        try {
            $routes($this);
        } finally {
            $this->prefix = $previousPrefix;
            $this->groupMiddleware = $previousMiddleware;
        }
    }

    /**
     * @return array{route: Route, params: array<string, string>}
     *
     * @throws HttpException 404 si aucune route, 405 si la méthode n'est pas permise
     */
    public function match(string $method, string $path): array
    {
        $method = strtoupper($method);
        $allowed = [];
        foreach ($this->routes as $route) {
            $params = $route->match($path);
            if ($params === null) {
                continue;
            }
            if (in_array($method, $route->methods, true) || ($method === 'HEAD' && in_array('GET', $route->methods, true))) {
                return ['route' => $route, 'params' => $params];
            }
            array_push($allowed, ...$route->methods);
        }
        if ($allowed !== []) {
            $allowed = array_values(array_unique($allowed));

            throw new HttpException(405, 'method_not_allowed', '', ['Allow' => implode(', ', $allowed)]);
        }

        throw HttpException::notFound();
    }

    /**
     * @param array<string, string|int> $params
     */
    public function url(string $name, array $params = []): string
    {
        if (!isset($this->named[$name])) {
            throw new \InvalidArgumentException('Route inconnue : ' . $name);
        }

        return $this->named[$name]->url($params);
    }

    /** @return list<Route> */
    public function routes(): array
    {
        return $this->routes;
    }
}
