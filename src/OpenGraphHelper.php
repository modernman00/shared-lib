<?php

declare(strict_types=1);

namespace Src;

/**
 * OpenGraphHelper
 * Standardized Open Graph & Twitter Card metadata generator for social crawlers
 * (WhatsApp, LinkedIn, X, Facebook, Slack, iMessage).
 *
 * Part of modernman00/shared-lib
 */
class OpenGraphHelper
{
    /**
     * Renders standard Open Graph and Twitter Card HTML meta tags.
     *
     * @param array<string, mixed> $options
     * @return string Formatted HTML meta tags
     */
    public static function render(array $options): string
    {
        $title = (string) ($options['title'] ?? '');
        $description = (string) ($options['description'] ?? '');
        $url = (string) ($options['url'] ?? '');
        $image = (string) ($options['image'] ?? '');
        $imageType = (string) ($options['image_type'] ?? 'image/png');
        $imageWidth = (int) ($options['image_width'] ?? 1200);
        $imageHeight = (int) ($options['image_height'] ?? 630);
        $imageAlt = (string) ($options['image_alt'] ?? $title);
        $siteName = (string) ($options['site_name'] ?? '');
        $type = (string) ($options['type'] ?? 'website');
        $twitterCard = (string) ($options['twitter_card'] ?? 'summary_large_image');
        $twitterSite = (string) ($options['twitter_site'] ?? '');
        $twitterCreator = (string) ($options['twitter_creator'] ?? '');

        $tags = [];

        // 1. Core Open Graph Tags
        if ($title !== '') {
            $tags[] = self::metaProperty('og:title', $title);
        }
        if ($description !== '') {
            $tags[] = self::metaProperty('og:description', $description);
        }
        if ($url !== '') {
            $tags[] = self::metaProperty('og:url', $url);
        }
        if ($siteName !== '') {
            $tags[] = self::metaProperty('og:site_name', $siteName);
        }
        $tags[] = self::metaProperty('og:type', $type);

        // 2. High-Resolution Social Image Tags
        if ($image !== '') {
            $tags[] = self::metaProperty('og:image', $image);
            if (str_starts_with($image, 'https://')) {
                $tags[] = self::metaProperty('og:image:secure_url', $image);
            }
            if ($imageType !== '') {
                $tags[] = self::metaProperty('og:image:type', $imageType);
            }
            if ($imageWidth > 0) {
                $tags[] = self::metaProperty('og:image:width', (string) $imageWidth);
            }
            if ($imageHeight > 0) {
                $tags[] = self::metaProperty('og:image:height', (string) $imageHeight);
            }
            if ($imageAlt !== '') {
                $tags[] = self::metaProperty('og:image:alt', $imageAlt);
            }
        }

        // 3. Twitter Card Tags
        $tags[] = self::metaName('twitter:card', $twitterCard);
        if ($title !== '') {
            $tags[] = self::metaName('twitter:title', $title);
        }
        if ($description !== '') {
            $tags[] = self::metaName('twitter:description', $description);
        }
        if ($image !== '') {
            $tags[] = self::metaName('twitter:image', $image);
            if ($imageAlt !== '') {
                $tags[] = self::metaName('twitter:image:alt', $imageAlt);
            }
        }
        if ($twitterSite !== '') {
            $tags[] = self::metaName('twitter:site', $twitterSite);
        }
        if ($twitterCreator !== '') {
            $tags[] = self::metaName('twitter:creator', $twitterCreator);
        }

        return implode("\n    ", $tags);
    }

    /**
     * Escapes content safely for HTML attribute values
     */
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Helper to render a <meta property="..." content="..."> tag
     */
    private static function metaProperty(string $property, string $content): string
    {
        return '<meta property="' . self::escape($property) . '" content="' . self::escape($content) . '" />';
    }

    /**
     * Helper to render a <meta name="..." content="..."> tag
     */
    private static function metaName(string $name, string $content): string
    {
        return '<meta name="' . self::escape($name) . '" content="' . self::escape($content) . '" />';
    }
}
