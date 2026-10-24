<?php

declare(strict_types=1);

namespace App\Core\Console;

/**
 * Commande de bin/console.
 */
abstract class Command
{
    public const int SUCCESS = 0;

    public const int FAILURE = 1;

    abstract public static function name(): string;

    abstract public static function description(): string;

    /**
     * Aide détaillée (options) affichée par « bin/console help <commande> ».
     */
    public static function usage(): string
    {
        return '';
    }

    abstract public function run(Input $input, Output $output): int;
}
