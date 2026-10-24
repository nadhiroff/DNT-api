<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database\SqlSplitter;
use PHPUnit\Framework\TestCase;

final class SqlSplitterTest extends TestCase
{
    public function testSplitsAndIgnoresComments(): void
    {
        $sql = <<<'SQL'
            -- commentaire ; ignoré
            CREATE TABLE a (id INT); # autre ; commentaire
            /* bloc ; */ INSERT INTO a VALUES (1);
            SQL;
        self::assertSame(['CREATE TABLE a (id INT)', 'INSERT INTO a VALUES (1)'], array_map(
            static fn (string $s): string => (string) preg_replace('/\s+/', ' ', trim($s)),
            SqlSplitter::split($sql),
        ));
    }

    public function testSemicolonsInsideQuotesAreKept(): void
    {
        $statements = SqlSplitter::split("INSERT INTO t VALUES ('a;b', \"c;d\", 'it''s; ok', 'e\\';f'); SELECT `x;y` FROM t");
        self::assertCount(2, $statements);
        self::assertStringContainsString("'e\\';f'", $statements[0]);
        self::assertSame('SELECT `x;y` FROM t', $statements[1]);
    }

    public function testTriggerWithSignalIsOneStatement(): void
    {
        $sql = "CREATE TRIGGER t BEFORE UPDATE ON a FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'non; non';";
        self::assertCount(1, SqlSplitter::split($sql));
    }

    public function testDoubleDashWithoutSpaceIsNotAComment(): void
    {
        self::assertSame(['SELECT 1--1'], SqlSplitter::split('SELECT 1--1'));
    }

    public function testRealMigrationsSplitCleanly(): void
    {
        foreach (glob(dirname(__DIR__, 2) . '/database/migrations/*.sql') ?: [] as $file) {
            foreach (SqlSplitter::split((string) file_get_contents($file)) as $statement) {
                self::assertMatchesRegularExpression('/^(CREATE|INSERT|DROP|ALTER|UPDATE)\b/i', $statement, basename($file));
            }
        }
    }
}
