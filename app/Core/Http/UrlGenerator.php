<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Config;

/**
 * Génère les URL internes en tenant compte du sous-dossier d'installation et de la présence
 * (ou non) de la réécriture d'URL. Sans réécriture, les liens passent par « index.php/chemin ».
 */
final class UrlGenerator
{
    private string $basePath;

    private string $front;

    private string $assetBase;

    public function __construct(
        private readonly Config $config,
        private readonly string $publicDirectory,
    ) {
        // Hors requête HTTP (console, e-mails) : déduit du chemin de APP_URL.
        $path = parse_url($config->string('app.url'), PHP_URL_PATH);
        $this->basePath = is_string($path) ? rtrim($path, '/') : '';
        $this->front = $this->basePath;
        $this->assetBase = $this->basePath . '/assets';
    }

    public function setRequest(Request $request): void
    {
        $this->basePath = $request->basePath();
        $script = $request->server('SCRIPT_NAME');
        $this->front = !$this->rewriteEnabled($request) && is_string($script) && $script !== ''
            ? $this->basePath . '/' . basename($script)
            : $this->basePath;

        // Script exécuté depuis public/ : les assets sont à côté ; sinon (index.php racine) dans public/.
        $filename = $request->server('SCRIPT_FILENAME');
        $scriptDir = is_string($filename) ? realpath(dirname($filename)) : false;
        $inPublic = $scriptDir !== false && $scriptDir === realpath($this->publicDirectory);
        $this->assetBase = $this->basePath . ($inPublic ? '/assets' : '/public/assets');
    }

    /**
     * @param array<string, scalar> $query
     */
    public function to(string $path, array $query = []): string
    {
        if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
            throw new \InvalidArgumentException('Chemin interne attendu : ' . $path);
        }
        $url = ($path === '/' && $this->front !== $this->basePath ? $this->front : $this->front . $path);
        $url = $url === '' ? '/' : $url;

        return $query === [] ? $url : $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public function asset(string $path): string
    {
        return $this->assetBase . '/' . ltrim($path, '/');
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * URL absolue (liens d'e-mails, documentation de l'API).
     *
     * @param array<string, scalar> $query
     */
    public function absolute(string $path, array $query = []): string
    {
        $origin = (string) preg_replace('#^(https?://[^/]+).*$#i', '$1', $this->config->string('app.url'));

        return $origin . $this->to($path, $query);
    }

    private function rewriteEnabled(Request $request): bool
    {
        $setting = strtolower($this->config->string('app.url_rewrite', 'auto'));
        if ($setting !== 'auto') {
            return filter_var($setting, FILTER_VALIDATE_BOOL);
        }
        if (PHP_SAPI === 'cli-server') {
            return true;
        }
        if ($request->isViaScript()) {
            return false;
        }
        // Positionnée par les .htaccess fournis (RewriteRule … [E=NOVABASE_REWRITE:1]) ou par Nginx.
        foreach (['NOVABASE_REWRITE', 'REDIRECT_NOVABASE_REWRITE', 'REDIRECT_REDIRECT_NOVABASE_REWRITE'] as $key) {
            if ($request->server($key) === '1') {
                return true;
            }
        }

        return false;
    }
}
