<?php

declare(strict_types=1);

namespace App\Core\Log;

use DateTimeImmutable;
use DateTimeZone;
use Stringable;
use Throwable;

/**
 * Journal structuré : une ligne JSON par événement, un fichier par jour (rotation naturelle),
 * masquage automatique des champs sensibles.
 *
 * Les fichiers portent l'extension .log.php et commencent par une ligne PHP qui stoppe
 * l'exécution : même sur un hébergement qui exposerait storage/, leur contenu n'est jamais servi.
 * Lecture : `tail -n +2 storage/logs/app-AAAA-MM-JJ.log.php`.
 */
final class Logger
{
    private const array LEVELS = ['debug' => 100, 'info' => 200, 'notice' => 250, 'warning' => 300, 'error' => 400, 'critical' => 500];

    private const string REDACTED = '[masqué]';

    public const string GUARD = "<?php http_response_code(404); exit; ?>\n";

    private const array SENSITIVE = ['password', 'pass', 'passwd', 'secret', 'token', 'apikey', 'api_key', 'master_key', 'private_key', 'authorization', 'cookie', 'totp', 'totp_code', 'recovery_code', 'otp'];

    private ?string $requestId = null;

    public function __construct(
        private readonly string $directory,
        private readonly string $minLevel = 'info',
        private readonly string $channel = 'app',
    ) {
    }

    public function setRequestId(?string $requestId): void
    {
        $this->requestId = $requestId;
    }

    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function log(string $level, string $message, array $context = []): void
    {
        $weight = self::LEVELS[$level] ?? self::LEVELS['info'];
        if ($weight < (self::LEVELS[$this->minLevel] ?? self::LEVELS['info'])) {
            return;
        }
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $record = [
            'ts' => $now->format('Y-m-d\TH:i:s.vP'),
            'level' => $level,
            'channel' => $this->channel,
            'msg' => $message,
        ];
        if ($this->requestId !== null) {
            $record['request_id'] = $this->requestId;
        }
        if ($context !== []) {
            $record['context'] = self::sanitize($context);
        }
        $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($line === false) {
            return;
        }
        $this->write($now->format('Y-m-d'), $line . "\n");
    }

    private function write(string $day, string $line): void
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0750, true);
        }
        $file = $this->directory . '/' . $this->channel . '-' . $day . '.log.php';
        if (!is_file($file)) {
            $line = self::GUARD . $line;
        }
        if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
            // Dernier recours : le journal système (stderr pour PHP-FPM / CLI).
            error_log(rtrim($line));
        }
    }

    /**
     * @param array<mixed> $context
     *
     * @return array<mixed>
     */
    public static function sanitize(array $context, int $depth = 0): array
    {
        $clean = [];
        foreach ($context as $key => $value) {
            if (is_string($key) && self::isSensitive($key)) {
                $clean[$key] = self::REDACTED;
                continue;
            }
            $clean[$key] = match (true) {
                $value instanceof Throwable => self::exception($value),
                is_array($value) => $depth < 5 ? self::sanitize($value, $depth + 1) : '[…]',
                $value instanceof Stringable => (string) $value,
                is_object($value) => '[' . $value::class . ']',
                is_resource($value) => '[resource]',
                default => $value,
            };
        }

        return $clean;
    }

    /** @return array<string, mixed> */
    public static function exception(Throwable $e): array
    {
        $data = [
            'class' => $e::class,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile() . ':' . $e->getLine(),
            'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 25),
        ];
        if ($e->getPrevious() !== null) {
            $data['previous'] = self::exception($e->getPrevious());
        }

        return $data;
    }

    private static function isSensitive(string $key): bool
    {
        $normalized = strtolower($key);
        foreach (self::SENSITIVE as $needle) {
            if ($normalized === $needle || str_ends_with($normalized, '_' . $needle) || str_starts_with($normalized, $needle . '_') || str_ends_with($normalized, $needle . '_enc') || str_ends_with($normalized, '_hash')) {
                return true;
            }
        }

        return false;
    }
}
