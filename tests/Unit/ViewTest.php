<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\Http\UrlGenerator;
use App\Core\Security\CspNonce;
use App\Core\View\View;
use PHPUnit\Framework\TestCase;

final class ViewTest extends TestCase
{
    private static function view(): View
    {
        $root = dirname(__DIR__, 2);
        $urls = new UrlGenerator(new Config(['app' => ['url' => 'http://localhost']]), $root . '/public');

        return new View($root . '/views', $root . '/public', new CspNonce(), $urls);
    }

    public function testEscapeHelper(): void
    {
        self::assertSame('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', e('<script>alert("x")</script>'));
        self::assertSame('&#039; onmouseover=&#039;x', e("' onmouseover='x"));
        self::assertSame('', e(null));
        self::assertSame("\u{FFFD}", e("\xFF"));
    }

    public function testErrorPageEscapesAndUsesLayout(): void
    {
        $html = self::view()->render('errors/error', [
            'appName' => 'NovaBase',
            'status' => 404,
            'message' => '<img src=x onerror=alert(1)>',
            'requestId' => 'req1',
            'debug' => null,
        ]);
        self::assertStringContainsString('<!doctype html>', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        self::assertStringNotContainsString('<img src=x', $html);
        self::assertStringContainsString('Erreur 404 · NovaBase', $html);
    }

    public function testInlineScriptsCarryNonce(): void
    {
        $view = self::view();
        $html = $view->render('errors/error', ['appName' => 'N', 'status' => 500, 'message' => 'm', 'requestId' => 'r', 'debug' => null]);
        preg_match_all('/<script\b[^>]*>/', $html, $scripts);
        self::assertNotEmpty($scripts[0]);
        foreach ($scripts[0] as $tag) {
            self::assertStringContainsString('nonce="' . $view->nonce() . '"', $tag);
        }
        self::assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $html, 'Les styles en ligne sont bloqués par la CSP.');
    }

    public function testTemplateNameTraversalIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        self::view()->render('../config/app');
    }

    /**
     * Garde-fou statique : toute sortie « <?= » d'un gabarit doit passer par e() ou par un
     * helper de Template qui renvoie du HTML déjà échappé.
     */
    public function testAllTemplateOutputsAreEscaped(): void
    {
        $allowed = '/^<\?=\s*(e\(|\$this->(section|insert|csrfField|csrfToken|nonce|asset|url|icon)\()/';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/views', \FilesystemIterator::SKIP_DOTS));
        $count = 0;
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            preg_match_all('/<\?=[^\n]*/', (string) file_get_contents($file->getPathname()), $matches);
            foreach ($matches[0] as $output) {
                ++$count;
                self::assertMatchesRegularExpression($allowed, $output, 'Sortie non échappée dans ' . $file->getFilename() . ' : ' . $output);
            }
            self::assertStringNotContainsString('<?php echo', (string) file_get_contents($file->getPathname()), $file->getFilename());
        }
        self::assertGreaterThan(0, $count);
    }
}
