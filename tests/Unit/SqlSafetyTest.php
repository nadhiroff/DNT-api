<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Critère d'acceptation : aucune requête SQL construite par concaténation de valeurs.
 * Toute concaténation d'une variable dans une chaîne SQL est refusée, sauf identifiant
 * explicitement validé et marqué « sql-identifier-validated » sur la ligne précédente.
 */
final class SqlSafetyTest extends TestCase
{
    private const string SQL_START = '/([\'"])\s*(SELECT|INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP|GRANT|REVOKE|USE|TRUNCATE|RENAME|SHOW)\b[^\'"]*\1\s*\.\s*\$/i';

    private const string SQL_INTERPOLATION = '/"\s*(SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP|GRANT)\b[^"]*(\$[a-z_]|\{\$)/i';

    public function testNoSqlConcatenationWithVariables(): void
    {
        $violations = [];
        $root = dirname(__DIR__, 2);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/app', \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $lines = file($file->getPathname()) ?: [];
            foreach ($lines as $i => $line) {
                if (preg_match(self::SQL_START, $line) !== 1 && preg_match(self::SQL_INTERPOLATION, $line) !== 1) {
                    continue;
                }
                $previous = $lines[$i - 1] ?? '';
                if (str_contains($previous, 'sql-identifier-validated')) {
                    continue;
                }
                $violations[] = substr($file->getPathname(), strlen($root) + 1) . ':' . ($i + 1) . '  ' . trim($line);
            }
        }
        self::assertSame([], $violations, "Concaténation SQL détectée :\n" . implode("\n", $violations));
    }

    public function testNoSqlInControllersOrViews(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['app/Controllers', 'views'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                    $content = (string) file_get_contents($file->getPathname());
                    self::assertDoesNotMatchRegularExpression('/[\'"]\s*(SELECT|INSERT INTO|UPDATE \w+ SET|DELETE FROM)\b/i', $content, $file->getPathname());
                    self::assertStringNotContainsString('PDO', $content, $file->getPathname());
                }
            }
        }
    }

    public function testEmulatedPreparesAreDisabled(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Core/Database/ConnectionFactory.php');
        self::assertStringContainsString('PDO::ATTR_EMULATE_PREPARES => false', $source);
        self::assertStringContainsString('PDO::ERRMODE_EXCEPTION', $source);
        self::assertStringContainsString('charset=utf8mb4', $source);
    }
}
