<?php

declare(strict_types=1);

namespace App\Core\Console;

/**
 * Arguments de ligne de commande et saisie interactive (y compris masquée pour les mots de passe).
 */
final class Input
{
    /** @var array<string, string|true> */
    private array $options = [];

    /** @var list<string> */
    private array $arguments = [];

    /** @var resource */
    private $stream;

    /**
     * @param list<string>  $argv   arguments après le nom de la commande
     * @param resource|null $stream flux de lecture (STDIN par défaut)
     */
    public function __construct(array $argv, $stream = null, private readonly ?bool $interactive = null)
    {
        $this->stream = $stream ?? STDIN;
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--')) {
                $parts = explode('=', substr($arg, 2), 2);
                $this->options[$parts[0]] = $parts[1] ?? true;
            } else {
                $this->arguments[] = $arg;
            }
        }
    }

    public function option(string $name): ?string
    {
        $value = $this->options[$name] ?? null;

        return is_string($value) ? $value : null;
    }

    public function flag(string $name): bool
    {
        return isset($this->options[$name]);
    }

    public function argument(int $index): ?string
    {
        return $this->arguments[$index] ?? null;
    }

    public function isInteractive(): bool
    {
        return $this->interactive ?? stream_isatty($this->stream);
    }

    public function readLine(): ?string
    {
        $line = fgets($this->stream);

        return $line === false ? null : rtrim($line, "\r\n");
    }

    public function ask(Output $output, string $question, ?string $default = null): string
    {
        $output->write($question . ($default !== null ? ' [' . $default . ']' : '') . ' : ');
        $answer = trim($this->readLine() ?? '');

        return $answer === '' ? ($default ?? '') : $answer;
    }

    /**
     * Saisie masquée (écho désactivé via stty quand le flux est un terminal).
     */
    public function secret(Output $output, string $question): string
    {
        $output->write($question . ' : ');
        $tty = stream_isatty($this->stream) && DIRECTORY_SEPARATOR === '/';
        if ($tty) {
            shell_exec('stty -echo');
        }
        try {
            $answer = $this->readLine() ?? '';
        } finally {
            if ($tty) {
                shell_exec('stty echo');
                $output->line('');
            }
        }

        return $answer;
    }

    public function confirm(Output $output, string $question, bool $default = false): bool
    {
        $answer = strtolower($this->ask($output, $question . ($default ? ' (O/n)' : ' (o/N)')));
        if ($answer === '') {
            return $default;
        }

        return in_array($answer, ['o', 'oui', 'y', 'yes'], true);
    }
}
