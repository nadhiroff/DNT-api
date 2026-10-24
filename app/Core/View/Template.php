<?php

declare(strict_types=1);

namespace App\Core\View;

use RuntimeException;
use Throwable;

/**
 * Contexte d'exécution d'un gabarit : layout, sections, inclusion de composants.
 * Dans un gabarit, $this désigne cette classe.
 */
final class Template
{
    private ?string $layout = null;

    /** @var array<string, mixed> */
    private array $layoutData = [];

    /** @var array<string, string> */
    private array $sections = [];

    /** @var list<string> */
    private array $sectionStack = [];

    /** @var array<string, true> sections définies par ce gabarit (et non héritées) */
    private array $ownSections = [];

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $sections sections héritées du gabarit enfant
     */
    public function __construct(
        private readonly View $view,
        private readonly string $name,
        private readonly array $data,
        array $sections = [],
    ) {
        $this->sections = $sections;
    }

    public function render(): string
    {
        $content = $this->capture($this->view->path($this->name), $this->data);
        if ($this->layout === null) {
            return $content;
        }
        $sections = $this->sections;
        // Le contenu transmis au parent est la sortie de ce niveau, sauf section « content » explicite.
        if (!isset($this->ownSections['content'])) {
            $sections['content'] = $content;
        }
        $parent = new self($this->view, $this->layout, [...$this->data, ...$this->layoutData], $sections);

        return $parent->render();
    }

    /** @param array<string, mixed> $data */
    public function layout(string $name, array $data = []): void
    {
        $this->layout = $name;
        $this->layoutData = $data;
    }

    public function start(string $section): void
    {
        $this->sectionStack[] = $section;
        ob_start();
    }

    public function stop(): void
    {
        $section = array_pop($this->sectionStack);
        if ($section === null) {
            throw new RuntimeException('stop() appelé sans start().');
        }
        $this->sections[$section] = (string) ob_get_clean();
        $this->ownSections[$section] = true;
    }

    /**
     * Contenu d'une section déjà rendue (HTML produit par nos gabarits, donc déjà échappé).
     */
    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    /**
     * Inclut un composant (views/components/…) avec ses propres données.
     *
     * @param array<string, mixed> $data
     */
    public function insert(string $name, array $data = []): string
    {
        return (new self($this->view, $name, [...$this->data, ...$data]))->render();
    }

    public function nonce(): string
    {
        return e($this->view->nonce());
    }

    public function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e($this->view->csrfToken()) . '">';
    }

    public function csrfToken(): string
    {
        return e($this->view->csrfToken());
    }

    /**
     * Icône SVG de la liste blanche App\Core\View\Icons.
     */
    public function icon(string $name, string $class = 'size-5'): string
    {
        return Icons::render($name, $class);
    }

    public function asset(string $path, bool $versioned = true): string
    {
        return e($this->view->asset($path, $versioned));
    }

    /**
     * URL interne (préfixe d'installation et « index.php » ajoutés si nécessaire), échappée.
     */
    public function url(string $path): string
    {
        return e($this->view->url($path));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function capture(string $file, array $data): string
    {
        $level = ob_get_level();
        ob_start();
        try {
            (function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })->call($this, $file, $data);

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
    }
}
