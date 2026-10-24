<?php

declare(strict_types=1);

namespace App\Core\Http;

use RuntimeException;
use Throwable;

/**
 * Exception transportant un statut HTTP et un code d'erreur machine.
 * Le message est destiné à l'utilisateur final : il ne doit jamais contenir de détail interne.
 */
class HttpException extends RuntimeException
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>  $details
     */
    public function __construct(
        private readonly int $status,
        private readonly string $errorCode,
        string $message = '',
        private readonly array $headers = [],
        private readonly array $details = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status, $previous);
    }

    public static function notFound(string $message = 'Ressource introuvable.'): self
    {
        return new self(404, 'not_found', $message);
    }

    public static function forbidden(string $message = 'Accès refusé.'): self
    {
        return new self(403, 'forbidden', $message);
    }

    public static function badRequest(string $message = 'Requête invalide.', string $code = 'bad_request'): self
    {
        return new self(400, $code, $message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        return $this->details;
    }

    public static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Requête invalide.',
            401 => 'Authentification requise.',
            402 => 'Quota dépassé.',
            403 => 'Accès refusé.',
            404 => 'Ressource introuvable.',
            405 => 'Méthode non autorisée.',
            409 => 'Conflit avec l\'état actuel de la ressource.',
            413 => 'Corps de requête trop volumineux.',
            415 => 'Type de contenu non supporté.',
            419 => 'Session expirée, veuillez recharger la page.',
            422 => 'Données invalides.',
            429 => 'Trop de requêtes.',
            503 => 'Service temporairement indisponible.',
            default => 'Erreur interne du serveur.',
        };
    }
}
