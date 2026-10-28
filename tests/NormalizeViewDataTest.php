<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use eftec\bladeone\BladeOne;

/**
 * Tests for normalizeViewData() in helpers/helpers.php.
 * Ensures view data with encoded or double-encoded apostrophes/quotes
 * (e.g. "Jummy's Birthday", "jummy&#039;s birthday", "jummy&amp;#039;s birthday")
 * are normalised to clean UTF-8 before Blade template compilation, preventing
 * double-escaping, corrupt strings in JavaScript/Canvas/WebShare, and bad rendering.
 */
class NormalizeViewDataTest extends TestCase
{
    public function testApostropheCleanStringRemainsIntact(): void
    {
        $input = "Jummy's Birthday";
        $this->assertSame("Jummy's Birthday", normalizeViewData($input));
    }

    public function testDecodesHtmlEntityApostrophe(): void
    {
        $input = "Jummy&#039;s Birthday";
        $this->assertSame("Jummy's Birthday", normalizeViewData($input));

        $input2 = "Jummy&#39;s Birthday";
        $this->assertSame("Jummy's Birthday", normalizeViewData($input2));

        $input3 = "Jummy&apos;s Birthday";
        $this->assertSame("Jummy's Birthday", normalizeViewData($input3));

        $input4 = "Jummy&#x27;s Birthday";
        $this->assertSame("Jummy's Birthday", normalizeViewData($input4));
    }

    public function testDecodesDoubleEncodedApostrophe(): void
    {
        $input = "Jummy&amp;#039;s Birthday";
        $this->assertSame("Jummy's Birthday", normalizeViewData($input));

        $input2 = "Jummy&amp;apos;s Birthday";
        $this->assertSame("Jummy's Birthday", normalizeViewData($input2));

        $input3 = "Jummy&amp;#39;s Birthday";
        $this->assertSame("Jummy's Birthday", normalizeViewData($input3));
    }

    public function testDecodesTypographicApostrophe(): void
    {
        $input = "Jummy&rsquo;s Birthday";
        $this->assertSame("Jummy’s Birthday", normalizeViewData($input));

        $input2 = "Jummy&#8217;s Birthday";
        $this->assertSame("Jummy’s Birthday", normalizeViewData($input2));
    }

    public function testDecodesQuoteEntities(): void
    {
        $input = "Welcome to &quot;The Gala&quot;";
        $this->assertSame('Welcome to "The Gala"', normalizeViewData($input));
    }

    public function testRecursivelyNormalisesNestedArrays(): void
    {
        $data = [
            'event' => [
                'name' => "Jummy&#039;s Birthday",
                'details' => [
                    'theme' => "Jummy&amp;#039;s Retro Night",
                    'host' => "Wally &amp; Jummy",
                ]
            ],
            'count' => 42,
            'is_active' => true,
            'nothing' => null,
        ];

        $expected = [
            'event' => [
                'name' => "Jummy's Birthday",
                'details' => [
                    'theme' => "Jummy's Retro Night",
                    'host' => "Wally &amp; Jummy",
                ]
            ],
            'count' => 42,
            'is_active' => true,
            'nothing' => null,
        ];

        $this->assertSame($expected, normalizeViewData($data));
    }

    public function testSecurityPreservesHtmlTagEntitiesToPreventXss(): void
    {
        $input = "&lt;script&gt;alert(&#039;XSS&#039;)&lt;/script&gt;";
        // &lt; and &gt; MUST NOT be decoded to < and >
        $expected = "&lt;script&gt;alert('XSS')&lt;/script&gt;";
        $this->assertSame($expected, normalizeViewData($input));
    }

    public function testBladeOneTemplateRenderingIntegration(): void
    {
        $cacheDir = sys_get_temp_dir() . '/blade_cache_' . uniqid();
        $viewDir = sys_get_temp_dir() . '/blade_views_' . uniqid();
        mkdir($cacheDir);
        mkdir($viewDir);

        $template = "BODY: {{ \$event['name'] }}\n" .
                    "ATTR: <input value=\"{{ \$event['name'] }}\">\n" .
                    "DATA_ATTR: <button data-name=\"{{ htmlspecialchars(\$event['name'], ENT_QUOTES, 'UTF-8') }}\"></button>\n" .
                    "JS: <script>const title = @json(\$event['name']);</script>";

        file_put_contents($viewDir . '/test_event.blade.php', $template);

        $blade = new BladeOne($viewDir, $cacheDir, BladeOne::MODE_DEBUG);

        // Raw input with legacy HTML entity from database
        $rawData = [
            'event' => [
                'name' => "Jummy&#039;s Birthday",
            ]
        ];

        $normalised = normalizeViewData($rawData);
        $output = $blade->run('test_event', $normalised);

        // 1. In HTML body, Blade safely escapes for browser display
        $this->assertStringContainsString('BODY: Jummy&#039;s Birthday', $output);

        // 2. In input attribute, it's safely escaped without double-encoding
        $this->assertStringContainsString('ATTR: <input value="Jummy&#039;s Birthday">', $output);

        // 3. In data attribute with htmlspecialchars, no &amp;#039; double-encoding
        $this->assertStringContainsString('DATA_ATTR: <button data-name="Jummy&#039;s Birthday"></button>', $output);

        // 4. In JS via @json, JS receives the actual string with apostrophe
        $this->assertStringContainsString('JS: <script>const title = "Jummy\u0027s Birthday";</script>', $output);

        // Cleanup
        @unlink($viewDir . '/test_event.blade.php');
        @rmdir($viewDir);
        @array_map('unlink', glob("$cacheDir/*") ?: []);
        @rmdir($cacheDir);
    }
}
