<?php

declare(strict_types=1);

namespace App\Core\View;

use App\Core\Http\UrlGenerator;
use App\Core\Security\CspNonce;
use App\Core\Security\Csrf;
use RuntimeException;

/**
 * Moteur de gabarits PHP. Les variables sont injectées dans une portée isolée et toute sortie
 * doit passer par e() ; le test ViewEscapingTest refuse tout « <?= » non échappé.
 */
final class View
{
    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(
        private readonly string $directory,
        private readonly string $publicDirectory,
        private readonly CspNonce $nonce,
        private readonly UrlGenerator $urls,
        private readonly ?Csrf $csrf = null,
    ) {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $tpl = new Template($this, $template, [...$this->shared, ...$data]);

        return $tpl->render();
    }

    public function path(string $template): string
    {
        if (preg_match('#^[a-z0-9_\-]+(/[a-z0-9_\-]+)*$#', $template) !== 1) {
            throw new RuntimeException('Nom de gabarit invalide : ' . $template);
        }
        $file = $this->directory . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('Gabarit introuvable : ' . $template);
        }

        return $file;
    }

    public function nonce(): string
    {
        return $this->nonce->value();
    }

    public function csrfToken(): string
    {
        if ($this->csrf === null) {
            throw new RuntimeException('CSRF indisponible dans ce contexte.');
        }

        return $this->csrf->token();
    }

    public function url(string $path): string
    {
        return $this->urls->to($path);
    }

    /**
     * URL d'un fichier de public/assets, avec empreinte de version pour le cache navigateur
     * (sauf pour les ressources aussi référencées par la CSS, comme les polices préchargées).
     */
    public function asset(string $path, bool $versioned = true): string
    {
        $path = ltrim($path, '/');
        if (str_contains($path, '..')) {
            throw new RuntimeException('Chemin d\'asset invalide.');
        }
        if (!$versioned) {
            return $this->urls->asset($path);
        }
        $file = $this->publicDirectory . '/assets/' . $path;
        $version = is_file($file) ? substr(hash_file('xxh3', $file) ?: '', 0, 10) : '0';

        return $this->urls->asset($path) . '?v=' . $version;
    }
}
