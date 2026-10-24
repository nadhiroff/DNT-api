<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Log\Logger;
use App\Core\Session\Session;
use App\Core\Validation\ValidationException;
use App\Core\View\View;
use ErrorException;
use Throwable;

/**
 * Gestionnaire global : convertit les erreurs PHP en exceptions, journalise et produit une
 * réponse propre (JSON pour l'API, page HTML pour le web). Aucune trace n'est jamais affichée
 * en production.
 */
final class ErrorHandler
{
    public function __construct(
        private readonly Logger $logger,
        private readonly bool $debug,
        private readonly ?View $view = null,
        private readonly ?Session $session = null,
    ) {
    }

    public function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', $this->debug && PHP_SAPI === 'cli' ? '1' : '0');
        ini_set('log_errors', '1');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                $this->logger->critical('Erreur fatale', ['error' => $error]);
                if (PHP_SAPI !== 'cli' && !headers_sent()) {
                    http_response_code(500);
                    header('Content-Type: text/plain; charset=UTF-8');
                    echo 'Erreur interne du serveur.';
                }
            }
        });
    }

    public function render(Request $request, Throwable $e): Response
    {
        if ($e instanceof ValidationException) {
            return $this->renderValidation($request, $e);
        }

        if ($e instanceof HttpException) {
            $status = $e->status();
            $code = $e->errorCode();
            $message = $e->getMessage();
            $headers = $e->headers();
            $details = $e->details();
            if ($status >= 500) {
                $this->report($request, $e);
            }
        } else {
            $this->report($request, $e);
            $status = 500;
            $code = 'internal_error';
            $message = HttpException::defaultMessage(500);
            $headers = [];
            $details = $this->debug ? ['exception' => $e::class, 'message' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()] : [];
        }

        $headers['X-Request-Id'] = $request->requestId();

        if ($request->wantsJson()) {
            return Response::jsonError($status, $code, $message, $details, $headers);
        }

        return $this->renderHtml($status, $message, $request->requestId(), $this->debug && !$e instanceof HttpException ? $e : null, $headers);
    }

    public function report(Request $request, Throwable $e): void
    {
        $this->logger->error($e->getMessage(), [
            'exception' => $e,
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
        ]);
    }

    private function renderValidation(Request $request, ValidationException $e): Response
    {
        if ($request->wantsJson()) {
            return Response::jsonError(422, 'validation_failed', $e->getMessage(), ['fields' => $e->errors()]);
        }
        // Formulaire web : retour à la page précédente (même origine) avec erreurs et saisie.
        if ($this->session !== null && $this->session->isStarted()) {
            $this->session->flash('errors', $e->errors());
            $this->session->flash('old', $e->old());
        }
        $back = self::sameOriginReferer($request) ?? $request->path();

        return Response::redirect($back, 303);
    }

    /**
     * @param array<string, string> $headers
     */
    private function renderHtml(int $status, string $message, string $requestId, ?Throwable $debugException, array $headers): Response
    {
        $html = null;
        if ($this->view !== null) {
            try {
                $html = $this->view->render('errors/error', [
                    'status' => $status,
                    'message' => $message,
                    'requestId' => $requestId,
                    'debug' => $debugException,
                ]);
            } catch (Throwable $renderError) {
                $this->logger->error('Échec du rendu de la page d\'erreur', ['exception' => $renderError]);
            }
        }
        $html ??= '<!doctype html><html lang="fr"><meta charset="utf-8"><title>Erreur ' . $status . '</title>'
            . '<p>' . e($message) . '</p><p><small>Référence : ' . e($requestId) . '</small></p></html>';

        return Response::html($html, $status, $headers);
    }

    private static function sameOriginReferer(Request $request): ?string
    {
        $referer = $request->referer();
        if ($referer === null) {
            return null;
        }
        $parts = parse_url($referer);
        if (!is_array($parts) || !isset($parts['host'])) {
            return null;
        }
        $authority = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if ($authority !== $request->header('host')) {
            return null;
        }
        $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');

        return Response::isSafeRedirect($path) ? $path : null;
    }
}
