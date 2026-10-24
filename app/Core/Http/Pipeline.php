<?php

declare(strict_types=1);

namespace App\Core\Http;

use Closure;

/**
 * Exécute une chaîne de middlewares puis le gestionnaire final.
 */
final class Pipeline implements RequestHandlerInterface
{
    /**
     * @param list<MiddlewareInterface>  $middlewares
     * @param Closure(Request): Response $destination
     */
    public function __construct(
        private readonly array $middlewares,
        private readonly Closure $destination,
        private readonly int $index = 0,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!isset($this->middlewares[$this->index])) {
            return ($this->destination)($request);
        }

        return $this->middlewares[$this->index]->process(
            $request,
            new self($this->middlewares, $this->destination, $this->index + 1),
        );
    }
}
