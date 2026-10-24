<?php

declare(strict_types=1);

namespace App\Core\Database;

/**
 * Découpe un script SQL de migration en instructions, en respectant chaînes, identifiants
 * entre backticks et commentaires. N'est utilisé que pour des fichiers versionnés du dépôt.
 */
final class SqlSplitter
{
    /** @return list<string> */
    public static function split(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $quote = null;

        for ($i = 0; $i < $length; ++$i) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($quote !== null) {
                $buffer .= $char;
                if ($char === '\\' && $quote !== '`') {
                    $buffer .= $next;
                    ++$i;
                } elseif ($char === $quote) {
                    if ($next === $quote) {
                        // Guillemet doublé (échappement SQL standard).
                        $buffer .= $next;
                        ++$i;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            // MySQL n'interprète « -- » comme commentaire que s'il est suivi d'un blanc ou de la fin du texte.
            $after = substr($sql, $i + 2, 1);
            if ($char === '-' && $next === '-' && ($after === '' || ctype_space($after))) {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $length : $end;
                $buffer .= "\n";
                continue;
            }
            if ($char === '#') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $length : $end;
                $buffer .= "\n";
                continue;
            }
            if ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $length : $end + 1;
                $buffer .= ' ';
                continue;
            }
            if ($char === '\'' || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === ';') {
                self::push($statements, $buffer);
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        self::push($statements, $buffer);

        return $statements;
    }

    /** @param list<string> $statements */
    private static function push(array &$statements, string $buffer): void
    {
        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }
    }
}
