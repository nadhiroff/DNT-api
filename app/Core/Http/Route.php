<?php

declare(strict_types=1);

namespace App\Core\Http;

/**
 * Définition d'une route compilée en expression régulière.
 */
final class Route
{
    /** Paramètre « {nom} » ou « {nom:contrainte} » ; la contrainte peut contenir des quantificateurs {n,m}. */
    private const string PARAM = '\{([a-z_][a-z0-9_]*)(?::((?:[^{}]|\{\d+(?:,\d*)?\})+))?\}';

    private string $regex;

    /** @var list<string> */
    private array $paramNames = [];

    /**
     * @param list<string>               $methods
     * @param array{0: class-string, 1: string} $handler
     * @param list<class-string<MiddlewareInterface>> $middleware
     */
    public function __construct(
        public readonly array $methods,
        public readonly string $pattern,
        public readonly array $handler,
        public readonly array $middleware = [],
        public readonly ?string $name = null,
    ) {
        $this->regex = $this->compile($pattern);
    }

    /**
     * Retourne les paramètres extraits, ou null si le chemin ne correspond pas.
     *
     * @return array<string, string>|null
     */
    public function match(string $path): ?array
    {
        if (preg_match($this->regex, $path, $matches) !== 1) {
            return null;
        }
        $params = [];
        foreach ($this->paramNames as $name) {
            $params[$name] = $matches[$name];
        }

        return $params;
    }

    /**
     * @param array<string, string|int> $params
     */
    public function url(array $params = []): string
    {
        $url = (string) preg_replace_callback(
            '#' . self::PARAM . '#i',
            static function (array $m) use ($params): string {
                if (!array_key_exists($m[1], $params)) {
                    throw new \InvalidArgumentException('Paramètre de route manquant : ' . $m[1]);
                }

                return rawurlencode((string) $params[$m[1]]);
            },
            $this->pattern,
        );

        return $url;
    }

    private function compile(string $pattern): string
    {
        $regex = preg_replace_callback(
            '#' . self::PARAM . '|([^{]+)#i',
            function (array $m): string {
                if ($m[3] !== null) {
                    return preg_quote($m[3], '#');
                }
                $name = (string) $m[1];
                $this->paramNames[] = $name;

                return '(?P<' . $name . '>' . ($m[2] ?? '[^/]+') . ')';
            },
            $pattern,
            -1,
            $count,
            PREG_UNMATCHED_AS_NULL,
        );

        return '#^' . $regex . '$#u';
    }
}
