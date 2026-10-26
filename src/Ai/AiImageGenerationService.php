<?php

declare(strict_types=1);

namespace Src\Ai;

/**
 * AI Generative Visual Studio Service
 *
 * Employs state-of-the-art open-weights diffusion models on Hugging Face serverless inference,
 * backed by a zero-latency, high-resolution procedural vector art engine.
 *
 * Invariant: Visual art is decoupled from text overlays. Generates pristine, atmospheric
 * central compositions so that the Canvas renderer can superimpose razor-sharp typography.
 */
class AiImageGenerationService
{
    private HuggingFaceInferenceGateway $gateway;

    /**
     * Curated celebration style presets (16 Diverse Themes)
     *
     * @var array<string, array{
     *     name: string,
     *     category: string,
     *     prompt_suffix: string,
     *     negative_prompt: string,
     *     colors: array<string>,
     *     accent: string
     * }>
     */
    public const STYLE_PRESETS = [
        'luxury_gold_black' => [
            'name' => '👑 VIP Rooftop & Luxury Gold',
            'category' => 'Luxury & Galas',
            'prompt_suffix' => ', luxury matte black and reflective gold foil accents, champagne bubbles, geometric art deco border, elegant evening gala ambience, empty minimalist center, 8k photography, sophisticated lighting',
            'negative_prompt' => 'text, messy, cluttered center, watermark, cheap, pixelated, neon, childish',
            'colors' => ['#0d0d11', '#1a1829', '#2d2208'],
            'accent' => '#d4af37',
        ],
        'elegant_gold_luxury' => [
            'name' => '👑 VIP Rooftop & Luxury Gold',
            'category' => 'Luxury & Galas',
            'prompt_suffix' => ', luxury matte black and reflective gold foil accents, champagne bubbles, geometric art deco border, elegant evening gala ambience, empty minimalist center, 8k photography, sophisticated lighting',
            'negative_prompt' => 'text, messy, cluttered center, watermark, cheap, pixelated, neon, childish',
            'colors' => ['#0d0d11', '#1a1829', '#2d2208'],
            'accent' => '#d4af37',
        ],
        'neon_cyber_party' => [
            'name' => '🪩 Neon Cyber Rave',
            'category' => 'Nightlife & Raves',
            'prompt_suffix' => ', vibrant neon lighting, cyberpunk party aesthetic, laser beams, energetic celebration, atmospheric haze, wide empty central area, cinematic 8k resolution, award-winning digital art',
            'negative_prompt' => 'text, typography, letters, words, watermark, crowded center, blurry, low resolution, ugly, distorted faces',
            'colors' => ['#090919', '#150826', '#25003e'],
            'accent' => '#00f2fe',
        ],
        'retro_disco_funk' => [
            'name' => '🕺 Studio 54 Retro Disco',
            'category' => 'Nightlife & Raves',
            'prompt_suffix' => ', 1970s retro disco ball, vibrant warm orange and magenta hues, sparkling lights, funky celebration vibes, clean central space for poster text, vintage vinyl aesthetic',
            'negative_prompt' => 'text, words, logo, blurry, dull, boring, overcrowded center',
            'colors' => ['#1e0524', '#3d0a33', '#5c1028'],
            'accent' => '#ff007f',
        ],
        'royal_gala' => [
            'name' => '✨ Grand Royal Gala',
            'category' => 'Luxury & Galas',
            'prompt_suffix' => ', royal imperial ballroom, crystal chandeliers, emerald green velvet, polished marble floors, sparkling gold confetti, majestic evening gala backdrop',
            'negative_prompt' => 'text, watermark, messy, low resolution, crowded center, cheap',
            'colors' => ['#041a14', '#082d23', '#0d4435'],
            'accent' => '#e2b755',
        ],
        'champagne_midnight' => [
            'name' => '🥂 Midnight Champagne Toast',
            'category' => 'Luxury & Galas',
            'prompt_suffix' => ', midnight blue night sky with cascading golden champagne effervescence, luxury glitter particles, sparkling crystal flutes in distance, luminous glowing atmosphere',
            'negative_prompt' => 'text, letters, words, messy, blurry, low quality',
            'colors' => ['#020b1e', '#061a3d', '#0d2858'],
            'accent' => '#f3d282',
        ],
        'speakeasy_jazz' => [
            'name' => '🎷 1920s Speakeasy Jazz',
            'category' => 'Luxury & Galas',
            'prompt_suffix' => ', vintage 1920s speakeasy lounge, warm brass trumpet silhouettes, art-deco sunburst arches, rich mahogany wood and amber warm glow, moody atmospheric haze',
            'negative_prompt' => 'text, modern neon, blurry, distorted, cheap, cluttered',
            'colors' => ['#190e06', '#2e1809', '#44230b'],
            'accent' => '#e5a65d',
        ],
        'venetian_masquerade' => [
            'name' => '🎭 Venetian Masquerade',
            'category' => 'Luxury & Galas',
            'prompt_suffix' => ', mysterious Venetian masquerade ball, ornate filigree gold masks in shadows, deep royal velvet purple curtains, floating starlight embers, gothic romantic glamour',
            'negative_prompt' => 'text, watermark, cartoon, bright daytime, messy center',
            'colors' => ['#11041c', '#200736', '#330c54'],
            'accent' => '#c084fc',
        ],
        'sunset_beach_club' => [
            'name' => '🌴 Sunset Beach Club',
            'category' => 'Summer & Outdoor',
            'prompt_suffix' => ', Balearic island sunset beach party, warm coral and golden orange horizon, calm turquoise ocean waves, subtle palm leaf silhouettes, ambient tiki lanterns',
            'negative_prompt' => 'text, dark, gloomy, snowy, blurry, messy, low quality',
            'colors' => ['#1f0a24', '#441437', '#7b203c'],
            'accent' => '#ff7e5f',
        ],
        'boiler_room' => [
            'name' => '🎧 Boiler Room Underground',
            'category' => 'Nightlife & Raves',
            'prompt_suffix' => ', industrial underground warehouse techno party, red floodlights, dark concrete walls, atmospheric mist, high-energy music vibe, minimalist dark aesthetic',
            'negative_prompt' => 'text, daytime, bright sunny, pastel, cartoon, messy center',
            'colors' => ['#0a0a0d', '#141419', '#24080b'],
            'accent' => '#ff3344',
        ],
        'ankara_heritage' => [
            'name' => '🥁 Vibrant Heritage Fiesta',
            'category' => 'Cultural & Milestone',
            'prompt_suffix' => ', vibrant African Ankara festival, rich warm terracotta, golden marigold, royal indigo geometric tribal motifs, talking drums in background, joyful cultural celebration',
            'negative_prompt' => 'text, dull, monochrome, sad, dark, messy center',
            'colors' => ['#240e04', '#4a1b05', '#6e2a09'],
            'accent' => '#f59e0b',
        ],
        'pastel_garden' => [
            'name' => '🌸 Romantic Pastel Garden',
            'category' => 'Cultural & Milestone',
            'prompt_suffix' => ', whimsical English garden party, blooming pastel blush peonies, eucalyptus botanical foliage, soft warm sun flares, glowing fairy light canopy',
            'negative_prompt' => 'text, dark, neon, gothic, industrial, blurry, cluttered',
            'colors' => ['#0d1a14', '#15291f', '#22382c'],
            'accent' => '#f472b6',
        ],
        'starlight_lantern' => [
            'name' => '🏮 Starlight Lantern Festival',
            'category' => 'Cultural & Milestone',
            'prompt_suffix' => ', enchanting nocturnal lantern festival, dozens of warm glowing paper lanterns floating into deep sapphire starry sky, water reflection below, magical peaceful aura',
            'negative_prompt' => 'text, daytime, bright sun, messy, noisy, low resolution',
            'colors' => ['#050d24', '#0b1940', '#15275e'],
            'accent' => '#fbbf24',
        ],
        'summer_bbq' => [
            'name' => '🍖 Sun-Drenched Garden BBQ',
            'category' => 'Summer & Outdoor',
            'prompt_suffix' => ', sunny garden barbecue party, warm honey amber sunlight, picnic string lights, rustic wooden textures, fresh green leaves, celebratory festive backyard warmth',
            'negative_prompt' => 'text, dark, rainy, cold, blurry, messy center',
            'colors' => ['#1c1305', '#33230a', '#4d3410'],
            'accent' => '#fb923c',
        ],
        'kids_storybook_dino' => [
            'name' => '🦖 Kids Dino Safari',
            'category' => 'Birthdays & Kids',
            'prompt_suffix' => ', friendly animated dinosaurs with party hats, balloons, lush prehistoric jungle, soft warm lighting, whimsical children book illustration style, uncluttered center banner area, charming pastel colours',
            'negative_prompt' => 'text, watermark, scary, aggressive, blood, dark, blurry, words, letters, malformed',
            'colors' => ['#061a10', '#0f301e', '#194d31'],
            'accent' => '#4ade80',
        ],
        'neon_balloons' => [
            'name' => '🎈 Neon Balloon Extravaganza',
            'category' => 'Birthdays & Kids',
            'prompt_suffix' => ', vivid metallic birthday balloons floating in studio, multi-colour confetti burst, vibrant violet and turquoise studio backdrop, sparkling celebration party energy',
            'negative_prompt' => 'text, boring, dull, grey, blurry, crowded center',
            'colors' => ['#120324', '#240842', '#380e64'],
            'accent' => '#ec4899',
        ],
        'cosmic_galaxy' => [
            'name' => '🚀 Cosmic Galaxy Starlight',
            'category' => 'Nightlife & Raves',
            'prompt_suffix' => ', deep space cosmic celebration, glowing purple and teal nebula swirls, twinkling distant starfields, celestial aurora borealis, vast cinematic sci-fi majesty',
            'negative_prompt' => 'text, daytime, earthly, blurry, messy center',
            'colors' => ['#03030d', '#08081c', '#101033'],
            'accent' => '#818cf8',
        ],
    ];

    public function __construct(?HuggingFaceInferenceGateway $gateway = null)
    {
        $this->gateway = $gateway ?? new HuggingFaceInferenceGateway();
    }

    /**
     * Generate an event poster or flyer background.
     *
     * @param string $userThemeDescription e.g. "Liam's 7th Birthday with T-Rex and balloons"
     * @param string $style Preset key (e.g. 'luxury_gold_black')
     * @param string $model
     * @return array{
     *     success: bool,
     *     data_uri: string,
     *     binary_bytes: string,
     *     style_used: string,
     *     engine: string,
     *     error?: string
     * }
     */
    public function generatePosterBackground(
        string $userThemeDescription,
        string $style = 'luxury_gold_black',
        string $model = 'black-forest-labs/FLUX.1-schnell'
    ): array {
        $trimmed = trim($userThemeDescription);
        if ($trimmed === '') {
            return [
                'success' => false,
                'data_uri' => '',
                'binary_bytes' => '',
                'style_used' => $style,
                'engine' => 'none',
                'error' => 'Theme description cannot be empty.',
            ];
        }

        // Match style or default to luxury_gold_black
        $preset = self::STYLE_PRESETS[$style] ?? self::STYLE_PRESETS['luxury_gold_black'];
        $composedPrompt = $trimmed . $preset['prompt_suffix'];

        $imageBytes = null;
        $engineUsed = 'diffusion_flux';
        try {
            // Tier 1: Attempt Hugging Face serverless diffusion
            $imageBytes = $this->gateway->generateImage($composedPrompt, $model, [
                'negative_prompt' => $preset['negative_prompt'],
                'num_inference_steps' => 4,
                'guidance_scale' => 7.5,
            ]);
        } catch (\Throwable $e) {
            $imageBytes = null;
        }

        // Tier 2: Serverless Open-Weights FLUX Diffusion Fallback (Pollinations FLUX Cluster)
        if (!is_string($imageBytes) || strlen($imageBytes) <= 500) {
            $imageBytes = $this->fetchServerlessFluxFallback($composedPrompt);
            if ($imageBytes !== null) {
                $engineUsed = 'serverless_flux_cluster';
            }
        }

        if (is_string($imageBytes) && strlen($imageBytes) > 500) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($imageBytes) ?: 'image/jpeg';
            if (str_starts_with($mime, 'image/')) {
                $dataUri = 'data:' . $mime . ';base64,' . base64_encode($imageBytes);

                return [
                    'success' => true,
                    'data_uri' => $dataUri,
                    'binary_bytes' => $imageBytes,
                    'style_used' => $style,
                    'engine' => $engineUsed,
                ];
            }
        }

        // Tier 3: Resilient Zero-Defect Procedural Engine: Generates high-resolution SVG vector artwork
        $proceduralSvgDataUri = $this->generateProceduralBackdrop($trimmed, $style);

        return [
            'success' => true,
            'data_uri' => $proceduralSvgDataUri,
            'binary_bytes' => '',
            'style_used' => $style,
            'engine' => 'procedural_vector_studio',
        ];
    }

    /**
     * Tier 2 Zero-Cost Open-Weights FLUX Diffusion Fallback.
     * Enforces Zero-PII scrubbing via AiPrivacyGuard before calling the serverless FLUX endpoint.
     *
     * @param string $prompt
     * @return string|null Raw image binary bytes or null on failure
     */
    private function fetchServerlessFluxFallback(string $prompt): ?string
    {
        $scrubbed = AiPrivacyGuard::scrubText(trim($prompt));
        if ($scrubbed === '') {
            return null;
        }

        $seed = abs(crc32($scrubbed . microtime())) % 999999;
        $url = 'https://image.pollinations.ai/prompt/' . rawurlencode($scrubbed)
            . '?width=800&height=1200&nologo=true&model=flux&seed=' . $seed;

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 18,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'PartyPlatform-AI-Studio/2.0',
            CURLOPT_HTTPHEADER     => ['Accept: image/jpeg, image/png, image/webp'],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        if (PHP_VERSION_ID < 80500) {
            @curl_close($ch);
        }

        if (
            is_string($response)
            && $httpCode >= 200
            && $httpCode < 300
            && strlen($response) > 1000
            && str_contains(strtolower($contentType), 'image/')
        ) {
            return $response;
        }

        return null;
    }

    /**
     * Generate an ultra-high resolution vector poster backdrop with layered gradients,
     * glowing radial spotlights, geometric framing, and celebratory particles.
     *
     * @param string $theme
     * @param string $styleKey
     * @return string Base64 SVG data URI
     */
    public function generateProceduralBackdrop(string $theme, string $styleKey): string
    {
        $preset = self::STYLE_PRESETS[$styleKey] ?? self::STYLE_PRESETS['luxury_gold_black'];
        $colors = $preset['colors'];
        $accent = $preset['accent'];

        $c0 = $colors[0];
        $c1 = $colors[1];
        $c2 = $colors[2];

        // Deterministic particle generation based on theme hash
        $hash = crc32($theme . $styleKey);
        mt_srand($hash);

        $particles = '';
        for ($i = 0; $i < 36; $i++) {
            $cx = mt_rand(40, 1160);
            $cy = mt_rand(40, 1760);
            $r = mt_rand(2, 9);
            $opacity = (mt_rand(20, 85) / 100);
            $particles .= sprintf(
                '<circle cx="%d" cy="%d" r="%d" fill="%s" opacity="%.2f" />' . "\n",
                $cx, $cy, $r, $accent, $opacity
            );
        }

        // Geometric corner framing flourishes
        $cornerAccents = sprintf('
            <!-- Corner Art Deco / Cyber Framing -->
            <rect x="30" y="30" width="1140" height="1740" rx="24" fill="none" stroke="%s" stroke-width="2" opacity="0.3" />
            <rect x="46" y="46" width="1108" height="1708" rx="16" fill="none" stroke="%s" stroke-width="1" opacity="0.15" />
            <circle cx="30" cy="30" r="12" fill="%s" opacity="0.5" />
            <circle cx="1170" cy="30" r="12" fill="%s" opacity="0.5" />
            <circle cx="30" cy="1770" r="12" fill="%s" opacity="0.5" />
            <circle cx="1170" cy="1770" r="12" fill="%s" opacity="0.5" />
        ', $accent, $accent, $accent, $accent, $accent, $accent);

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 1800" width="1200" height="1800">
  <defs>
    <linearGradient id="bgGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{$c0}" />
      <stop offset="50%" stop-color="{$c1}" />
      <stop offset="100%" stop-color="{$c2}" />
    </linearGradient>
    <radialGradient id="spotTop" cx="50%" cy="18%" r="48%">
      <stop offset="0%" stop-color="{$accent}" stop-opacity="0.35" />
      <stop offset="60%" stop-color="{$accent}" stop-opacity="0.08" />
      <stop offset="100%" stop-color="{$accent}" stop-opacity="0" />
    </radialGradient>
    <radialGradient id="spotBottom" cx="50%" cy="85%" r="55%">
      <stop offset="0%" stop-color="{$accent}" stop-opacity="0.25" />
      <stop offset="100%" stop-color="{$accent}" stop-opacity="0" />
    </radialGradient>
    <filter id="ambientBlur" x="-20%" y="-20%" width="140%" height="140%">
      <feGaussianBlur stdDeviation="80" />
    </filter>
  </defs>

  <!-- Deep Canvas Background -->
  <rect width="1200" height="1800" fill="url(#bgGrad)" />

  <!-- Atmospheric Glow Orbs -->
  <circle cx="600" cy="300" r="450" fill="url(#spotTop)" />
  <circle cx="600" cy="1550" r="500" fill="url(#spotBottom)" />
  <ellipse cx="250" cy="650" rx="350" ry="250" fill="{$accent}" opacity="0.12" filter="url(#ambientBlur)" />
  <ellipse cx="950" cy="1150" rx="380" ry="280" fill="{$c2}" opacity="0.3" filter="url(#ambientBlur)" />

  {$cornerAccents}

  <!-- Celebration Particles / Bokeh Starlight -->
  {$particles}
</svg>
SVG;

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Get list of available curated style presets with descriptions.
     *
     * @return array<string, array{name: string, category: string}>
     */
    public static function getAvailableStyles(): array
    {
        $list = [];
        foreach (self::STYLE_PRESETS as $key => $meta) {
            $list[$key] = [
                'name' => $meta['name'],
                'category' => $meta['category'],
            ];
        }
        return $list;
    }
}
