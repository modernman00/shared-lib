<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\SeoEngine;

class SeoEngineTest extends TestCase
{
    public function testRenderHeadBasicTags(): void
    {
        $html = SeoEngine::renderHead([
            'title' => 'Test App | Best Tool',
            'description' => 'A great testing tool for modern teams.',
            'canonical' => 'https://example.com/tool',
        ]);

        $this->assertStringContainsString('<title>Test App | Best Tool</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="A great testing tool for modern teams.">', $html);
        $this->assertStringContainsString('<meta name="robots" content="index, follow', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://example.com/tool">', $html);
    }

    public function testRenderHeadWithOpenGraph(): void
    {
        $html = SeoEngine::renderHead([
            'title' => 'Social App',
            'description' => 'Connect with friends.',
            'canonical' => 'https://example.com/social',
            'og' => [
                'image' => 'https://example.com/social.png',
                'site_name' => 'SocialApp',
            ],
        ]);

        $this->assertStringContainsString('property="og:title" content="Social App"', $html);
        $this->assertStringContainsString('property="og:url" content="https://example.com/social"', $html);
        $this->assertStringContainsString('property="og:image" content="https://example.com/social.png"', $html);
    }

    public function testRenderJsonLdGraphAndSitelinks(): void
    {
        $html = SeoEngine::renderHead([
            'title' => 'iDecide App',
            'schema' => [
                'organization' => [
                    'name' => 'iDecide Inc',
                    'url' => 'https://idecide.app',
                    'logo' => 'https://idecide.app/logo.png',
                ],
                'website' => [
                    'name' => 'iDecide',
                    'url' => 'https://idecide.app',
                    'search_url_template' => 'https://idecide.app/search?q={search_term_string}',
                ],
                'site_navigation' => [
                    ['name' => 'Decision Matrix', 'url' => 'https://idecide.app/matrix'],
                    ['name' => 'Pricing', 'url' => 'https://idecide.app/pricing'],
                ],
                'breadcrumbs' => [
                    ['name' => 'Home', 'url' => 'https://idecide.app'],
                    ['name' => 'Features', 'url' => 'https://idecide.app/features'],
                ],
            ],
        ]);

        $this->assertStringContainsString('<script type="application/ld+json">', $html);
        $this->assertStringContainsString('"@type": "Organization"', $html);
        $this->assertStringContainsString('"@type": "WebSite"', $html);
        $this->assertStringContainsString('"@type": "SearchAction"', $html);
        $this->assertStringContainsString('"@type": "SiteNavigationElement"', $html);
        $this->assertStringContainsString('"@type": "BreadcrumbList"', $html);
    }

    public function testGenerateSitemapSuccess(): void
    {
        $urls = [
            ['loc' => 'https://partyplatform.com/', 'priority' => 1.0, 'changefreq' => 'daily'],
            ['loc' => 'https://partyplatform.com/features', 'priority' => 0.8],
        ];

        $xml = SeoEngine::generateSitemap($urls);

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<loc>https://partyplatform.com/</loc>', $xml);
        $this->assertStringContainsString('<loc>https://partyplatform.com/features</loc>', $xml);
    }

    public function testGenerateSitemapRejectsLocalTestHostnames(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Production sitemap cannot include local dev hostname');

        $urls = [
            ['loc' => 'http://lef.test/calculator'],
        ];

        SeoEngine::generateSitemap($urls);
    }

    public function testGenerateRobotsTxt(): void
    {
        $robots = SeoEngine::generateRobotsTxt('https://familyhub.app/sitemap.xml', ['/custom-private/']);

        $this->assertStringContainsString('User-agent: *', $robots);
        $this->assertStringContainsString('Disallow: /admin/', $robots);
        $this->assertStringContainsString('Disallow: /custom-private/', $robots);
        $this->assertStringContainsString('Sitemap: https://familyhub.app/sitemap.xml', $robots);
    }

    public function testGenerateFaqSchema(): void
    {
        $faqs = [
            [
                'question' => 'Is PartyPlatform free for guests?',
                'answer' => 'Yes! Guests RSVP and contribute to potlucks 100% free with zero registration.',
            ],
            [
                'question' => 'Can I collect money for group gifts?',
                'answer' => 'Yes, our built-in pledge tracker manages contributions securely.',
            ],
        ];

        $jsonLd = SeoEngine::generateFaqSchema($faqs);

        $this->assertStringContainsString('<script type="application/ld+json">', $jsonLd);
        $this->assertStringContainsString('"@type": "FAQPage"', $jsonLd);
        $this->assertStringContainsString('"@type": "Question"', $jsonLd);
        $this->assertStringContainsString('"name": "Is PartyPlatform free for guests?"', $jsonLd);
        $this->assertStringContainsString('"@type": "Answer"', $jsonLd);
        $this->assertStringContainsString('"text": "Yes! Guests RSVP and contribute to potlucks 100% free with zero registration."', $jsonLd);
    }

    public function testRenderFaqAccordionHtml(): void
    {
        $faqs = [
            [
                'question' => 'How does TenantScore protect my data?',
                'answer' => 'We use <strong>bank-grade 256-bit encryption</strong> and never sell tenant data.',
            ],
        ];

        $html = SeoEngine::renderFaqAccordionHtml($faqs);

        $this->assertStringContainsString('itemscope itemtype="https://schema.org/FAQPage"', $html);
        $this->assertStringContainsString('<details', $html);
        $this->assertStringContainsString('<summary', $html);
        $this->assertStringContainsString('How does TenantScore protect my data?', $html);
        $this->assertStringContainsString('<strong>bank-grade 256-bit encryption</strong>', $html);
    }
}
