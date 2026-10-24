<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Session\Session;
use App\Core\View\View;

/**
 * Raccourcis des contrôleurs web : rendu d'une vue (avec erreurs, saisie précédente et messages
 * flash), redirections internes et messages flash.
 */
final class Responder
{
    public function __construct(
        private readonly View $view,
        private readonly UrlGenerator $urls,
        private readonly Session $session,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function view(Request $request, string $template, array $data = [], int $status = 200): Response
    {
        $errors = $this->session->getFlash('errors', []);
        $old = $this->session->getFlash('old', []);

        return Response::html($this->view->render($template, $data + [
            'errors' => is_array($errors) ? $errors : [],
            'old' => is_array($old) ? $old : [],
            'flash' => [
                'success' => $this->session->getFlash('success'),
                'error' => $this->session->getFlash('error'),
                'info' => $this->session->getFlash('info'),
            ],
            'currentUser' => $request->attribute('user'),
            'currentPath' => $request->path(),
            'withCsrf' => true,
        ]), $status);
    }

    /**
     * @param array<string, scalar> $query
     */
    public function redirect(string $path, array $query = []): Response
    {
        return Response::redirect($this->urls->to($path, $query), 303);
    }

    /**
     * Message flash affiché sur la page suivante (success | error | info).
     */
    public function flash(string $type, string $message): self
    {
        $this->session->flash($type, $message);

        return $this;
    }

    public function url(string $path): string
    {
        return $this->urls->to($path);
    }
}
