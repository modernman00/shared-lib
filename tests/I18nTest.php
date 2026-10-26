<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\I18n;

class I18nTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        I18n::reset();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        unset($_SESSION['app_locale']);
        unset($_COOKIE['app_locale']);
    }

    protected function tearDown(): void
    {
        I18n::reset();
        parent::tearDown();
    }

    public function testDefaultLocaleIsBritishEnglish(): void
    {
        $this->assertSame('en', I18n::getLocale());
        $this->assertSame('Home', I18n::trans('nav.home'));
        $this->assertSame('Create Your Account', I18n::trans('auth.create_account'));
        $this->assertSame('Save Changes', I18n::trans('global.save_changes'));
    }

    public function testLocaleSwitchingToFrench(): void
    {
        $this->assertTrue(I18n::setLocale('fr'));
        $this->assertSame('fr', I18n::getLocale());
        $this->assertSame('Accueil', I18n::trans('nav.home'));
        $this->assertSame('Créez votre compte', I18n::trans('auth.create_account'));
        $this->assertSame('Enregistrer les modifications', I18n::trans('global.save_changes'));
    }

    public function testLocaleSwitchingToSpanish(): void
    {
        $this->assertTrue(I18n::setLocale('es'));
        $this->assertSame('es', I18n::getLocale());
        $this->assertSame('Inicio', I18n::trans('nav.home'));
        $this->assertSame('Crea tu cuenta', I18n::trans('auth.create_account'));
        $this->assertSame('Guardar cambios', I18n::trans('global.save_changes'));
    }

    public function testLocaleSwitchingToAmericanEnglishWithDifferentialFallback(): void
    {
        $this->assertTrue(I18n::setLocale('us'));
        $this->assertSame('us', I18n::getLocale());

        // US English overrides
        $this->assertSame('Color', I18n::trans('global.colour'));
        $this->assertSame('Center', I18n::trans('global.centre'));
        $this->assertSame('Customize', I18n::trans('global.customise'));
        $this->assertSame('Cell Phone', I18n::trans('auth.mobile'));
        $this->assertSame('ZIP Code', I18n::trans('auth.postcode'));

        // Cascading fallback to British English for keys not in us.php
        $this->assertSame('Home', I18n::trans('nav.home'));
        $this->assertSame('Create Your Account', I18n::trans('auth.create_account'));
        $this->assertSame('Save Changes', I18n::trans('global.save_changes'));
    }

    public function testAdversarialLfiAndPathTraversalRejected(): void
    {
        $maliciousPayloads = [
            '../../etc/passwd',
            '..\\..\\windows\\system32',
            '%2e%2e%2f%2e%2e%2f',
            '../../../config/init.php',
            '<script>alert(1)</script>',
            "en' OR '1'='1",
            "en\0.php",
            '../../../etc/shadow',
        ];

        foreach ($maliciousPayloads as $payload) {
            $this->assertFalse(I18n::setLocale($payload), "Expected payload {$payload} to be rejected");
            $this->assertNotSame($payload, I18n::getLocale());
        }
    }

    public function testVariableInterpolation(): void
    {
        I18n::setLocale('en');
        // Test custom fallback with variable interpolation
        $msg1 = I18n::trans('greeting.colon', ['name' => 'Sarah'], 'Hello :name!');
        $this->assertSame('Hello Sarah!', $msg1);

        $msg2 = I18n::trans('greeting.brace', ['name' => 'Wally'], 'Welcome {name} to platform');
        $this->assertSame('Welcome Wally to platform', $msg2);
    }

    public function testRegisterCustomLocale(): void
    {
        I18n::registerLocale('yo', 'Yoruba', 'Èdè Yorùbá', '🇳🇬');
        $locales = I18n::getSupportedLocales();

        $this->assertArrayHasKey('yo', $locales);
        $this->assertSame('Èdè Yorùbá', $locales['yo']['native']);
        $this->assertSame('🇳🇬', $locales['yo']['flag']);

        $this->assertTrue(I18n::setLocale('yo'));
        $this->assertSame('yo', I18n::getLocale());
    }

    public function testFallbackCascadingWhenKeyMissingInActiveLocale(): void
    {
        I18n::setLocale('fr');
        // Key missing in both French and English dictionaries returns fallback or key itself
        $this->assertSame('Custom Default', I18n::trans('unknown.deep.key', [], 'Custom Default'));
        $this->assertSame('unknown.key', I18n::trans('unknown.key'));
    }

    public function testGlobalHelperFunctions(): void
    {
        I18n::setLocale('en');
        $this->assertSame('Home', __('nav.home'));
        $this->assertSame('Home', trans('nav.home'));

        I18n::setLocale('fr');
        $this->assertSame('Accueil', __('nav.home'));
        $this->assertSame('Accueil', trans('nav.home'));
    }

    public function testHasKeyMethod(): void
    {
        $this->assertTrue(I18n::has('nav.home'));
        $this->assertTrue(I18n::has('global.save'));
        $this->assertFalse(I18n::has('non.existent.translation.key.xyz'));
    }
}
