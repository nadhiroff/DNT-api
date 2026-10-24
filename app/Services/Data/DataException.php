<?php

declare(strict_types=1);

namespace App\Services\Data;

use App\Core\Http\HttpException;

/**
 * Erreur sur les données (validation, contrainte) : 400/409/422 avec détails par champ.
 */
final class DataException extends HttpException
{
    /**
     * @param array<string, string> $fields
     */
    public static function invalid(array $fields): self
    {
        return new self(422, 'validation_failed', 'Certaines valeurs sont invalides.', [], ['fields' => $fields]);
    }
}
