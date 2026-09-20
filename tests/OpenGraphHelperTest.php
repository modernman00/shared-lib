<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\OpenGraphHelper;

class OpenGraphHelperTest extends TestCase
{
    public function testRenderProducesStandardOpenGraphAndTwitterTags(): void
    {
        $metaHtml = OpenGraphHelper::render([
            'title' => 'Launch Strategy 2026',
            'description' => 'Comprehensive data-driven review of our Q3 expansion',
            'url' => 'https://platform.test/review/42',
            'image' => 'https://platform.test/images/og-card.png',
            'site_name' => 'Portfolio Platform',
            'twitter_card' => 'summary_large_image',
            'twitter_site' => '@portfolioteam',
        ]);

        $this->assertStringContainsString('<meta property="og:title" content="Launch Strategy 2026" />', $metaHtml);
        $this->assertStringContainsString('<meta property="og:description" content="Comprehensive data-driven review of our Q3 expansion" />', $metaHtml);
        $this->assertStringContainsString('<meta property="og:url" content="https://platform.test/review/42" />', $metaHtml);
        $this->assertStringContainsString('<meta property="og:image" content="https://platform.test/images/og-card.png" />', $metaHtml);
        $this->assertStringContainsString('<meta property="og:image:secure_url" content="https://platform.test/images/og-card.png" />', $metaHtml);
        $this->assertStringContainsString('<meta property="og:image:width" content="1200" />', $metaHtml);
        $this->assertStringContainsString('<meta property="og:image:height" content="630" />', $metaHtml);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image" />', $metaHtml);
        $this->assertStringContainsString('<meta name="twitter:site" content="@portfolioteam" />', $metaHtml);
    }

    public function testRenderEscapesSpecialCharactersAgainstXss(): void
    {
        $maliciousPayload = '"><script>alert("XSS")</script><a href="bad">';
        $metaHtml = OpenGraphHelper::render([
            'title' => $maliciousPayload,
            'description' => 'Test & Verify "Quotes"',
            'url' => 'https://platform.test/?q=' . urlencode($maliciousPayload),
        ]);

        $this->assertStringNotContainsString('<script>alert("XSS")</script>', $metaHtml);
        $this->assertStringContainsString('&quot;&gt;&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;&lt;a href=&quot;bad&quot;&gt;', $metaHtml);
        $this->assertStringContainsString('Test &amp; Verify &quot;Quotes&quot;', $metaHtml);
    }
}
