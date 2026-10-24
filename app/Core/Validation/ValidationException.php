<?php

declare(strict_types=1);

namespace App\Core\Validation;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    /**
     * @param array<string, list<string>> $errors
     * @param array<string, mixed>        $old    valeurs saisies (sans les mots de passe) pour réafficher le formulaire
     */
    public function __construct(private readonly array $errors, private readonly array $old = [])
    {
        parent::__construct('Les données envoyées sont invalides.');
    }

    /**
     * @param array<string, mixed> $old
     */
    public static function withMessage(string $field, string $message, array $old = []): self
    {
        return new self([$field => [$message]], $old);
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string, mixed> */
    public function old(): array
    {
        return $this->old;
    }
}
