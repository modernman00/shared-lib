<?php

declare(strict_types=1);

namespace Src;

/**
 * SeoEngine
 * High-performance, centralized SEO, Structured Data (JSON-LD),
 * Sitelinks Architecture, and XML Sitemap generator.
 *
 * Part of modernman00/shared-lib
 */
class SeoEngine
{
    /**
     * Renders complete SEO head tags including title, meta description, canonical,
     * robots directives, Open Graph, Twitter cards, and JSON-LD schema graphs.
     *
     * @param array<string, mixed> $options
     * @return string Valid HTML tags to insert inside <head>
     */
    public static function renderHead(array $options): string
    {
        $tags = [];

        // 1. Title Tag
        $title = (string) ($options['title'] ?? '');
        if ($title !== '') {
            $tags[] = '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
        }

        // 2. Primary Meta Description
        $description = (string) ($options['description'] ?? '');
        if ($description !== '') {
            $tags[] = '<meta name="description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">';
        }

        // 3. Robots Directives
        $robots = (string) ($options['robots'] ?? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1');
        if ($robots !== '') {
            $tags[] = '<meta name="robots" content="' . htmlspecialchars($robots, ENT_QUOTES, 'UTF-8') . '">';
        }

        // 4. Canonical Tag (Prevents duplicate content penalties)
        $canonical = (string) ($options['canonical'] ?? '');
        if ($canonical !== '') {
            $tags[] = '<link rel="canonical" href="' . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . '">';
        }

        // 5. OpenGraph & Twitter Cards (leveraging OpenGraphHelper)
        if (!empty($options['og']) && is_array($options['og'])) {
            /** @var array<string, mixed> $ogOptions */
            $ogOptions = $options['og'];
            // Fill default fallbacks from top-level options if not explicitly passed
            if (!isset($ogOptions['title']) && $title !== '') {
                $ogOptions['title'] = $title;
            }
            if (!isset($ogOptions['description']) && $description !== '') {
                $ogOptions['description'] = $description;
            }
            if (!isset($ogOptions['url']) && $canonical !== '') {
                $ogOptions['url'] = $canonical;
            }
            $tags[] = OpenGraphHelper::render($ogOptions);
        }

        // 6. Structured Data (JSON-LD)
        if (!empty($options['schema']) && is_array($options['schema'])) {
            /** @var array<string, mixed> $schemaOptions */
            $schemaOptions = $options['schema'];
            $jsonLd = self::renderJsonLd($schemaOptions);
            if ($jsonLd !== '') {
                $tags[] = $jsonLd;
            }
        }

        return implode("\n    ", $tags);
    }

    /**
     * Generates a unified JSON-LD schema graph for modern Google search and Sitelinks.
     *
     * @param array<string, mixed> $schemaOptions
     * @return string JSON-LD <script> block
     */
    public static function renderJsonLd(array $schemaOptions): string
    {
        $graph = [];

        // Organization Schema
        if (!empty($schemaOptions['organization']) && is_array($schemaOptions['organization'])) {
            $org = $schemaOptions['organization'];
            $orgData = [
                '@type' => 'Organization',
                '@id' => (string) ($org['url'] ?? '') . '#organization',
                'name' => (string) ($org['name'] ?? ''),
                'url' => (string) ($org['url'] ?? ''),
            ];
            if (!empty($org['logo'])) {
                $orgData['logo'] = [
                    '@type' => 'ImageObject',
                    'url' => (string) $org['logo'],
                ];
            }
            if (!empty($org['same_as']) && is_array($org['same_as'])) {
                $orgData['sameAs'] = array_values($org['same_as']);
            }
            $graph[] = $orgData;
        }

        // WebSite Schema (with optional Google Sitelinks Searchbox)
        if (!empty($schemaOptions['website']) && is_array($schemaOptions['website'])) {
            $ws = $schemaOptions['website'];
            $wsData = [
                '@type' => 'WebSite',
                '@id' => (string) ($ws['url'] ?? '') . '#website',
                'name' => (string) ($ws['name'] ?? ''),
                'url' => (string) ($ws['url'] ?? ''),
            ];
            if (!empty($schemaOptions['organization']['url'])) {
                $wsData['publisher'] = ['@id' => (string) $schemaOptions['organization']['url'] . '#organization'];
            }
            if (!empty($ws['search_url_template'])) {
                $wsData['potentialAction'] = [
                    '@type' => 'SearchAction',
                    'target' => [
                        '@type' => 'EntryPoint',
                        'urlTemplate' => (string) $ws['search_url_template'],
                    ],
                    'query-input' => 'required name=search_term_string',
                ];
            }
            $graph[] = $wsData;
        }

        // SoftwareApplication / WebApplication Schema
        if (!empty($schemaOptions['software_application']) && is_array($schemaOptions['software_application'])) {
            $app = $schemaOptions['software_application'];
            $appData = [
                '@type' => (string) ($app['type'] ?? 'WebApplication'),
                'name' => (string) ($app['name'] ?? ''),
                'operatingSystem' => (string) ($app['operating_system'] ?? 'All'),
                'applicationCategory' => (string) ($app['application_category'] ?? 'BusinessApplication'),
            ];
            if (!empty($app['description'])) {
                $appData['description'] = (string) $app['description'];
            }
            if (!empty($app['url'])) {
                $appData['url'] = (string) $app['url'];
            }
            if (!empty($app['offers']) && is_array($app['offers'])) {
                $appData['offers'] = [
                    '@type' => 'Offer',
                    'price' => (string) ($app['offers']['price'] ?? '0'),
                    'priceCurrency' => (string) ($app['offers']['currency'] ?? 'GBP'),
                ];
            }
            $graph[] = $appData;
        }

        // SiteNavigationElement Schema (Enables Google key links / sitelinks)
        if (!empty($schemaOptions['site_navigation']) && is_array($schemaOptions['site_navigation'])) {
            foreach ($schemaOptions['site_navigation'] as $navItem) {
                if (is_array($navItem) && !empty($navItem['name']) && !empty($navItem['url'])) {
                    $graph[] = [
                        '@type' => 'SiteNavigationElement',
                        'name' => (string) $navItem['name'],
                        'url' => (string) $navItem['url'],
                    ];
                }
            }
        }

        // BreadcrumbList Schema
        if (!empty($schemaOptions['breadcrumbs']) && is_array($schemaOptions['breadcrumbs'])) {
            $elements = [];
            $pos = 1;
            foreach ($schemaOptions['breadcrumbs'] as $crumb) {
                if (is_array($crumb) && !empty($crumb['name'])) {
                    $elements[] = [
                        '@type' => 'ListItem',
                        'position' => $pos++,
                        'name' => (string) $crumb['name'],
                        'item' => (string) ($crumb['url'] ?? ''),
                    ];
                }
            }
            if (!empty($elements)) {
                $graph[] = [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => $elements,
                ];
            }
        }

        // FAQPage Schema
        if (!empty($schemaOptions['faq']) && is_array($schemaOptions['faq'])) {
            $mainEntity = [];
            foreach ($schemaOptions['faq'] as $faqItem) {
                if (is_array($faqItem) && !empty($faqItem['question']) && !empty($faqItem['answer'])) {
                    $mainEntity[] = [
                        '@type' => 'Question',
                        'name' => trim(strip_tags((string) $faqItem['question'])),
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => trim(strip_tags((string) $faqItem['answer'])),
                        ],
                    ];
                }
            }
            if (!empty($mainEntity)) {
                $graph[] = [
                    '@type' => 'FAQPage',
                    'mainEntity' => $mainEntity,
                ];
            }
        }

        // Custom Schemas
        if (!empty($schemaOptions['custom']) && is_array($schemaOptions['custom'])) {
            foreach ($schemaOptions['custom'] as $custom) {
                if (is_array($custom)) {
                    $graph[] = $custom;
                }
            }
        }

        if (empty($graph)) {
            return '';
        }

        $schemaPayload = [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];

        return '<script type="application/ld+json">' . "\n" .
            json_encode($schemaPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) .
            "\n</script>";
    }

    /**
     * Generates a standalone FAQPage JSON-LD schema tag.
     *
     * @param array<int, array{question: string, answer: string}> $faqs
     * @return string Valid <script type="application/ld+json"> tag
     */
    public static function generateFaqSchema(array $faqs): string
    {
        return self::renderJsonLd(['faq' => $faqs]);
    }

    /**
     * Alias for renderJsonLd() for API consistency.
     *
     * @param array<string, mixed> $schemaOptions
     * @return string JSON-LD <script> block
     */
    public static function generateJsonLd(array $schemaOptions): string
    {
        return self::renderJsonLd($schemaOptions);
    }

    /**
     * Renders a semantic, accessible HTML FAQ accordion using <details> and <summary>.
     * Guarantees touch targets >= 44px, zero JavaScript runtime requirement, and WCAG AAA compliance.
     *
     * @param array<int, array{question: string, answer: string}> $faqs
     * @param array{class?: string, item_class?: string, question_class?: string, answer_class?: string} $options
     * @return string Semantic HTML accordion markup
     */
    public static function renderFaqAccordionHtml(array $faqs, array $options = []): string
    {
        if (empty($faqs)) {
            return '';
        }

        $containerClass = htmlspecialchars((string) ($options['class'] ?? 'seo-faq-accordion space-y-4 my-8'), ENT_QUOTES, 'UTF-8');
        $itemClass = htmlspecialchars((string) ($options['item_class'] ?? 'seo-faq-item group rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/70 dark:bg-slate-900/70 p-5 shadow-sm transition-all duration-200 hover:shadow-md'), ENT_QUOTES, 'UTF-8');
        $summaryClass = htmlspecialchars((string) ($options['question_class'] ?? 'seo-faq-question flex w-full cursor-pointer list-none items-center justify-between text-left text-base sm:text-lg font-semibold text-slate-900 dark:text-white min-h-[44px] focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500'), ENT_QUOTES, 'UTF-8');
        $answerClass = htmlspecialchars((string) ($options['answer_class'] ?? 'seo-faq-answer mt-3 text-sm sm:text-base leading-relaxed text-slate-650 dark:text-slate-350 pr-4'), ENT_QUOTES, 'UTF-8');

        $html = [];
        $html[] = "<div class=\"{$containerClass}\" itemscope itemtype=\"https://schema.org/FAQPage\">";

        foreach ($faqs as $faq) {
            $question = htmlspecialchars(trim((string) $faq['question']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $rawAnswer = trim((string) $faq['answer']);
            // Allow safe basic tags for formatting (strong, em, a, p, br)
            $safeAnswer = strip_tags($rawAnswer, '<strong><em><b><i><a><p><br><ul><li>');

            if ($question === '' || $safeAnswer === '') {
                continue;
            }

            $html[] = "  <details class=\"{$itemClass}\" itemscope itemprop=\"mainEntity\" itemtype=\"https://schema.org/Question\">";
            $html[] = "    <summary class=\"{$summaryClass}\">";
            $html[] = "      <span itemprop=\"name\">{$question}</span>";
            $html[] = "      <span class=\"seo-faq-icon ml-4 shrink-0 transition-transform duration-200 group-open:rotate-180\" aria-hidden=\"true\">";
            $html[] = "        <svg class=\"h-5 w-5 text-slate-500\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M19 9l-7 7-7-7\"/></svg>";
            $html[] = "      </span>";
            $html[] = "    </summary>";
            $html[] = "    <div class=\"{$answerClass}\" itemscope itemprop=\"acceptedAnswer\" itemtype=\"https://schema.org/Answer\">";
            $html[] = "      <div itemprop=\"text\">{$safeAnswer}</div>";
            $html[] = "    </div>";
            $html[] = "  </details>";
        }

        $html[] = '</div>';

        return implode("\n", $html) . "\n";
    }

    /**
     * Resolves the canonical public origin, e.g. "https://example.com" (no trailing slash).
     * Order of resolution:
     *   1. GEO_CANONICAL_URL explicit override
     *   2. APP_URL when it is a real (non-local, non-test) host
     *   3. APP_URL declared in .env_production / .env-production / .env.production
     *
     * @param string|null $projectRoot Root directory of the application
     * @return string Canonical origin (e.g. "https://loaneasyfinance.com")
     * @throws \RuntimeException when no production origin can be resolved.
     */
    public static function resolveProductionBaseUrl(?string $projectRoot = null): string
    {
        $root = $projectRoot ?? (defined('ROOT_DIR') ? (string) constant('ROOT_DIR') : dirname(__DIR__, 4));
        if (!is_dir($root)) {
            $root = getcwd() ?: '.';
        }

        $candidates = [
            $_ENV['GEO_CANONICAL_URL'] ?? getenv('GEO_CANONICAL_URL') ?: null,
            $_ENV['APP_URL'] ?? getenv('APP_URL') ?: null,
            self::readEnvProductionUrl($root),
        ];

        foreach ($candidates as $candidate) {
            $origin = self::normaliseOrigin($candidate);
            if ($origin !== null && !self::isLocalHost((string) parse_url($origin, PHP_URL_HOST))) {
                return $origin;
            }
        }

        throw new \RuntimeException(
            "Canonical site URL could not be resolved. Set GEO_CANONICAL_URL, a production APP_URL, "
            . "or provide APP_URL in .env_production."
        );
    }

    /**
     * Normalise a URL string to "scheme://host[:port]" or null if invalid.
     */
    public static function normaliseOrigin(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
            return null;
        }
        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        $origin = strtolower($parts['scheme']) . '://' . strtolower($parts['host']);
        if (!empty($parts['port'])) {
            $origin .= ':' . (int) $parts['port'];
        }

        return $origin;
    }

    /**
     * Checks if a hostname is local, test, or loopback.
     */
    public static function isLocalHost(string $host): bool
    {
        $host = strtolower(trim($host));
        if ($host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }
        $suffixes = ['.test', '.local', '.localhost', '.invalid', '.internal'];
        foreach ($suffixes as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Read only APP_URL from production env files in the specified directory.
     */
    private static function readEnvProductionUrl(string $root): ?string
    {
        foreach (['.env_production', '.env-production', '.env.production'] as $name) {
            $file = rtrim($root, '/') . '/' . $name;
            if (!is_readable($file)) {
                continue;
            }
            $contents = file_get_contents($file);
            if ($contents !== false && preg_match('/^\s*APP_URL\s*=\s*["\']?([^"\'\s#]+)/m', $contents, $m) === 1) {
                return $m[1];
            }
        }
        return null;
    }

    /**
     * Generates a valid XML Sitemap conforming to the sitemaps.org 0.9 protocol.
     * Prevents localhost/.test poisoning by strictly validating canonical hostnames.
     *
     * @param array<int, array{loc: string, lastmod?: string, changefreq?: string, priority?: string|float}> $urls
     * @return string XML sitemap
     * @throws \InvalidArgumentException If a URL is invalid or points to an unauthorized test domain
     */
    public static function generateSitemap(array $urls): string
    {
        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $entry) {
            $loc = trim((string) $entry['loc']);
            if ($loc === '') {
                continue;
            }

            // Strictly check for invalid .test or localhost domains
            $host = (string) parse_url($loc, PHP_URL_HOST);
            if (str_ends_with(strtolower($host), '.test') || str_ends_with(strtolower($host), '.local') || $host === 'localhost') {
                throw new \InvalidArgumentException("Production sitemap cannot include local dev hostname: {$loc}");
            }

            $lastmod = (string) ($entry['lastmod'] ?? date('Y-m-d'));
            $changefreq = (string) ($entry['changefreq'] ?? 'weekly');
            $priority = (string) ($entry['priority'] ?? '0.8');

            $xml[] = '  <url>';
            $xml[] = '    <loc>' . htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') . '</loc>';
            $xml[] = '    <lastmod>' . htmlspecialchars($lastmod, ENT_QUOTES, 'UTF-8') . '</lastmod>';
            $xml[] = '    <changefreq>' . htmlspecialchars($changefreq, ENT_QUOTES, 'UTF-8') . '</changefreq>';
            $xml[] = '    <priority>' . htmlspecialchars($priority, ENT_QUOTES, 'UTF-8') . '</priority>';
            $xml[] = '  </url>';
        }

        $xml[] = '</urlset>';

        return implode("\n", $xml) . "\n";
    }

    /**
     * Generates a standard, hardened robots.txt content with sitemap advertisement.
     *
     * @param string $sitemapUrl Authoritative public sitemap URL
     * @param array<int, string> $disallowedPaths Extra paths to disallow
     * @return string Content for robots.txt
     */
    public static function generateRobotsTxt(string $sitemapUrl, array $disallowedPaths = []): string
    {
        $defaultDisallow = [
            '/admin/',
            '/admin',
            '/api/',
            '/api',
            '/storage/',
            '/vendor/',
            '/bootstrap/',
            '/cron/',
            '/*.json$',
        ];

        $allDisallow = array_unique(array_merge($defaultDisallow, $disallowedPaths));

        $lines = [];
        $lines[] = 'User-agent: *';
        $lines[] = 'Allow: /';
        foreach ($allDisallow as $path) {
            $lines[] = 'Disallow: ' . $path;
        }
        $lines[] = '';
        $lines[] = '# Authoritative XML Sitemap';
        $lines[] = 'Sitemap: ' . $sitemapUrl;

        return implode("\n", $lines) . "\n";
    }
}
