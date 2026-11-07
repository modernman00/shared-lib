<?php

declare(strict_types=1);

namespace Tests\I18n;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Src\I18n;
use Src\PageTranslator;

/** Separate process: defines CSP_NONCE and changes the active locale. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PageTranslatorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/pt_' . bin2hex(random_bytes(4));
        mkdir($this->root . '/resources/lang/auto', 0777, true);
        file_put_contents($this->root . '/resources/lang/auto/fr.json', json_encode(['Sign In' => 'Connexion (auto)', 'Get Started' => 'Commencer', 'Evil' => '</script><script>alert(1)</script>']));
        file_put_contents($this->root . '/resources/lang/auto/overrides.fr.json', json_encode(['Sign In' => 'Se connecter']));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->root . '/resources/lang/auto/*') ?: []);
        @rmdir($this->root . '/resources/lang/auto');
        @rmdir($this->root . '/resources/lang');
        @rmdir($this->root . '/resources');
        @rmdir($this->root);
    }

    public function testEnglishVisitorsGetNoPayload(): void
    {
        I18n::setLocale('en');
        $this->assertSame('', PageTranslator::head($this->root));
        $this->assertSame('en-GB', PageTranslator::htmlLang());
    }

    public function testOverridesWinOverMachineTranslations(): void
    {
        $dict = PageTranslator::dictionary('fr', $this->root);
        $this->assertSame('Se connecter', $dict['Sign In']);
        $this->assertSame('Commencer', $dict['Get Started']);
    }

    public function testFrenchPayloadCarriesNonceAndCannotBreakOutOfTheScript(): void
    {
        define('CSP_NONCE', 'TestNonce12345');
        I18n::setLocale('fr');
        $html = PageTranslator::head($this->root);
        $this->assertStringContainsString('<script nonce="TestNonce12345">', $html);
        $this->assertStringContainsString('__ATG_I18N__', $html);
        $this->assertSame(1, substr_count(strtolower($html), '</script>'), 'dictionary text must not close the script early');
        $this->assertSame('fr', PageTranslator::htmlLang());
    }

    public function testUnsupportedLocaleFallsBackSafely(): void
    {
        I18n::setLocale('us');
        $this->assertSame('', PageTranslator::head($this->root));
        $this->assertSame('en-US', PageTranslator::htmlLang());
    }
}
