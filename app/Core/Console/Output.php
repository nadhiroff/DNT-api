<?php

declare(strict_types=1);

namespace App\Core\Console;

/**
 * Sortie console avec couleurs ANSI quand le terminal les supporte.
 */
final class Output
{
    /** @var resource */
    private $stream;

    private bool $colors;

    /** @param resource|null $stream */
    public function __construct($stream = null, ?bool $colors = null)
    {
        $this->stream = $stream ?? STDOUT;
        $this->colors = $colors ?? (stream_isatty($this->stream) && getenv('NO_COLOR') === false);
    }

    public function write(string $text): void
    {
        fwrite($this->stream, $text);
    }

    public function line(string $text = ''): void
    {
        $this->write($text . PHP_EOL);
    }

    public function info(string $text): void
    {
        $this->line($this->color('36', $text));
    }

    public function success(string $text): void
    {
        $this->line($this->color('32', '✔ ' . $text));
    }

    public function warning(string $text): void
    {
        $this->line($this->color('33', '! ' . $text));
    }

    public function error(string $text): void
    {
        $this->line($this->color('31', '✘ ' . $text));
    }

    public function bold(string $text): string
    {
        return $this->color('1', $text);
    }

    public function muted(string $text): string
    {
        return $this->color('2', $text);
    }

    /**
     * @param list<string>       $headers
     * @param list<list<string>> $rows
     */
    public function table(array $headers, array $rows): void
    {
        $widths = array_map('mb_strlen', $headers);
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, mb_strlen($cell));
            }
        }
        $render = static function (array $cells) use ($widths): string {
            $out = [];
            foreach ($widths as $i => $width) {
                $cell = (string) ($cells[$i] ?? '');
                $out[] = $cell . str_repeat(' ', $width - mb_strlen($cell));
            }

            return '  ' . implode('   ', $out);
        };
        $this->line($this->bold($render($headers)));
        foreach ($rows as $row) {
            $this->line($render($row));
        }
    }

    private function color(string $code, string $text): string
    {
        return $this->colors ? "\033[" . $code . 'm' . $text . "\033[0m" : $text;
    }
}
