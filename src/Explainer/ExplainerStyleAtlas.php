<?php

declare(strict_types=1);

namespace Src\Explainer;

/**
 * ExplainerStyleAtlas
 *
 * Curated library of deterministic visual style presets for code-driven
 * HTML5 Canvas & Web Audio explainer videos. Provides WCAG AA-checked
 * colour palettes, typography tokens, motion curves, background motifs,
 * procedural SFX profiles, and ready-to-copy prompt briefs.
 */
class ExplainerStyleAtlas
{
    public const DEFAULT_STYLE = 'glassmorphic-dark';

    /**
     * Returns all available visual style presets keyed by style ID.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'glassmorphic-dark' => [
                'id' => 'glassmorphic-dark',
                'name' => 'Glassmorphic Dark',
                'category' => 'saas',
                'description' => 'Deep navy canvas with frosted slate cards, crisp indigo accents, and smooth Bezier reveals.',
                'palette' => [
                    'bg' => '#0b1120',
                    'surface' => '#131c31',
                    'primary' => '#6366f1',
                    'secondary' => '#38bdf8',
                    'accent' => '#22d3ee',
                    'text' => '#f8fafc',
                    'muted' => '#94a3b8',
                ],
                'typography' => [
                    'headingFont' => 'Inter, system-ui, -apple-system, sans-serif',
                    'bodyFont' => 'Inter, system-ui, -apple-system, sans-serif',
                    'monoFont' => 'ui-monospace, SFMono-Regular, Menlo, monospace',
                    'headingWeight' => '800',
                ],
                'motion' => [
                    'easing' => 'cubic-bezier(0.22, 1, 0.36, 1)',
                    'transition' => 'zoom',
                    'motif' => 'grid',
                ],
                'sfxProfile' => [
                    'clickFreq' => 620,
                    'whooshDuration' => 0.28,
                    'chimeNotes' => [523.25, 659.25, 783.99],
                ],
                'promptBrief' => 'Dark navy (#0b1120) glassmorphic UI cards (#131c31) with indigo (#6366f1) and cyan (#22d3ee) highlights, subtle perspective grid, smooth ease-out zoom transitions, and crisp tactile clicks.',
            ],
            'swiss-editorial' => [
                'id' => 'swiss-editorial',
                'name' => 'Swiss Editorial',
                'category' => 'editorial',
                'description' => 'High-contrast off-white paper canvas with bold black typography, crimson focal rules, and clean slide cuts.',
                'palette' => [
                    'bg' => '#f8f7f4',
                    'surface' => '#ffffff',
                    'primary' => '#111827',
                    'secondary' => '#dc2626',
                    'accent' => '#b91c1c',
                    'text' => '#111827',
                    'muted' => '#4b5563',
                ],
                'typography' => [
                    'headingFont' => 'Inter, Helvetica Neue, Arial, sans-serif',
                    'bodyFont' => 'Inter, Helvetica Neue, Arial, sans-serif',
                    'monoFont' => 'ui-monospace, SFMono-Regular, Menlo, monospace',
                    'headingWeight' => '900',
                ],
                'motion' => [
                    'easing' => 'cubic-bezier(0.16, 1, 0.3, 1)',
                    'transition' => 'slide',
                    'motif' => 'dots',
                ],
                'sfxProfile' => [
                    'clickFreq' => 480,
                    'whooshDuration' => 0.20,
                    'chimeNotes' => [440.0, 554.37, 659.25],
                ],
                'promptBrief' => 'Warm off-white (#f8f7f4) Swiss grid layout with razor-sharp black (#111827) kinetic typography, crimson (#dc2626) accent bars, dot-matrix background, and snappy horizontal slide cuts.',
            ],
            'sovereign-fintech' => [
                'id' => 'sovereign-fintech',
                'name' => 'Sovereign FinTech',
                'category' => 'fintech',
                'description' => 'Executive charcoal surface with emerald ledger indicators and gold trust highlights.',
                'palette' => [
                    'bg' => '#090d16',
                    'surface' => '#111827',
                    'primary' => '#10b981',
                    'secondary' => '#f59e0b',
                    'accent' => '#34d399',
                    'text' => '#f9fafb',
                    'muted' => '#9ca3af',
                ],
                'typography' => [
                    'headingFont' => 'Inter, system-ui, sans-serif',
                    'bodyFont' => 'Inter, system-ui, sans-serif',
                    'monoFont' => 'ui-monospace, SFMono-Regular, Menlo, monospace',
                    'headingWeight' => '800',
                ],
                'motion' => [
                    'easing' => 'cubic-bezier(0.25, 0.1, 0.25, 1)',
                    'transition' => 'wipe',
                    'motif' => 'grid',
                ],
                'sfxProfile' => [
                    'clickFreq' => 700,
                    'whooshDuration' => 0.25,
                    'chimeNotes' => [587.33, 739.99, 880.0],
                ],
                'promptBrief' => 'Charcoal (#090d16) executive FinTech canvas with emerald (#10b981) growth metrics, amber (#f59e0b) callouts, tabular monospace numbers, and authoritative wipe transitions.',
            ],
            'clinical-clarity' => [
                'id' => 'clinical-clarity',
                'name' => 'Clinical Clarity',
                'category' => 'healthcare',
                'description' => 'Calm clinical slate-white canvas with teal trust markers designed for healthcare and CQC explainers.',
                'palette' => [
                    'bg' => '#f0fdfa',
                    'surface' => '#ffffff',
                    'primary' => '#0f766e',
                    'secondary' => '#0284c7',
                    'accent' => '#0d9488',
                    'text' => '#0f172a',
                    'muted' => '#475569',
                ],
                'typography' => [
                    'headingFont' => 'Inter, system-ui, sans-serif',
                    'bodyFont' => 'Inter, system-ui, sans-serif',
                    'monoFont' => 'ui-monospace, SFMono-Regular, Menlo, monospace',
                    'headingWeight' => '700',
                ],
                'motion' => [
                    'easing' => 'cubic-bezier(0.33, 1, 0.68, 1)',
                    'transition' => 'dissolve',
                    'motif' => 'waves',
                ],
                'sfxProfile' => [
                    'clickFreq' => 540,
                    'whooshDuration' => 0.32,
                    'chimeNotes' => [523.25, 659.25, 783.99],
                ],
                'promptBrief' => 'Calm pale-teal (#f0fdfa) healthcare canvas with deep teal (#0f766e) headings, high-contrast navy (#0f172a) body copy, gentle wave motif, and soft dissolve transitions.',
            ],
            'blueprint-architect' => [
                'id' => 'blueprint-architect',
                'name' => 'Blueprint Architect',
                'category' => 'technical',
                'description' => 'Engineering blueprint navy with crisp cyan vector lines, monospace labels, and technical callouts.',
                'palette' => [
                    'bg' => '#0c2340',
                    'surface' => '#123158',
                    'primary' => '#38bdf8',
                    'secondary' => '#bae6fd',
                    'accent' => '#facc15',
                    'text' => '#f0f9ff',
                    'muted' => '#93c5fd',
                ],
                'typography' => [
                    'headingFont' => 'ui-monospace, SFMono-Regular, Menlo, monospace',
                    'bodyFont' => 'Inter, system-ui, sans-serif',
                    'monoFont' => 'ui-monospace, SFMono-Regular, Menlo, monospace',
                    'headingWeight' => '700',
                ],
                'motion' => [
                    'easing' => 'cubic-bezier(0.2, 0.8, 0.2, 1)',
                    'transition' => 'wipe',
                    'motif' => 'blueprint',
                ],
                'sfxProfile' => [
                    'clickFreq' => 820,
                    'whooshDuration' => 0.22,
                    'chimeNotes' => [659.25, 830.61, 987.77],
                ],
                'promptBrief' => 'Architectural blueprint (#0c2340) background with cyan (#38bdf8) CAD grid lines, monospace technical labels, yellow (#facc15) cursor highlights, and precision wipe cuts.',
            ],
            'kinetic-mono' => [
                'id' => 'kinetic-mono',
                'name' => 'Kinetic Mono',
                'category' => 'creative',
                'description' => 'High-impact pure black and white kinetic typography with electric lime beat highlights.',
                'palette' => [
                    'bg' => '#000000',
                    'surface' => '#121212',
                    'primary' => '#a3e635',
                    'secondary' => '#ffffff',
                    'accent' => '#bef264',
                    'text' => '#ffffff',
                    'muted' => '#a1a1aa',
                ],
                'typography' => [
                    'headingFont' => 'Inter, system-ui, sans-serif',
                    'bodyFont' => 'ui-monospace, SFMono-Regular, Menlo, monospace',
                    'monoFont' => 'ui-monospace, SFMono-Regular, Menlo, monospace',
                    'headingWeight' => '900',
                ],
                'motion' => [
                    'easing' => 'cubic-bezier(0.19, 1, 0.22, 1)',
                    'transition' => 'kinetic-pop',
                    'motif' => 'particles',
                ],
                'sfxProfile' => [
                    'clickFreq' => 920,
                    'whooshDuration' => 0.16,
                    'chimeNotes' => [587.33, 880.0, 1174.66],
                ],
                'promptBrief' => 'Pure black (#000000) high-contrast kinetic typography with electric lime (#a3e635) word-level emphasis, subtle floating particles, and rapid kinetic-pop transitions.',
            ],
            'paper-whiteboard' => [
                'id' => 'paper-whiteboard',
                'name' => 'Paper Whiteboard',
                'category' => 'editorial',
                'description' => 'Clean studio whiteboard aesthetic with warm ink strokes and highlighter marker accents.',
                'palette' => [
                    'bg' => '#ffffff',
                    'surface' => '#f8fafc',
                    'primary' => '#1e293b',
                    'secondary' => '#eab308',
                    'accent' => '#2563eb',
                    'text' => '#0f172a',
                    'muted' => '#475569',
                ],
                'typography' => [
                    'headingFont' => 'Inter, system-ui, sans-serif',
                    'bodyFont' => 'Inter, system-ui, sans-serif',
                    'monoFont' => 'ui-monospace, SFMono-Regular, Menlo, monospace',
                    'headingWeight' => '800',
                ],
                'motion' => [
                    'easing' => 'cubic-bezier(0.4, 0, 0.2, 1)',
                    'transition' => 'slide',
                    'motif' => 'dots',
                ],
                'sfxProfile' => [
                    'clickFreq' => 440,
                    'whooshDuration' => 0.24,
                    'chimeNotes' => [440.0, 523.25, 659.25],
                ],
                'promptBrief' => 'Crisp white (#ffffff) whiteboard canvas with slate ink (#1e293b), cobalt (#2563eb) underlines, amber (#eab308) marker highlights, and clean slide transitions.',
            ],
            'warm-botanical' => [
                'id' => 'warm-botanical',
                'name' => 'Warm Botanical',
                'category' => 'creative',
                'description' => 'Organic cream and forest-green palette tailored for family, community, and lifestyle stories.',
                'palette' => [
                    'bg' => '#faf7f2',
                    'surface' => '#ffffff',
                    'primary' => '#166534',
                    'secondary' => '#d97706',
                    'accent' => '#15803d',
                    'text' => '#1c1917',
                    'muted' => '#57534e',
                ],
                'typography' => [
                    'headingFont' => 'Georgia, Cambria, Times New Roman, serif',
                    'bodyFont' => 'Inter, system-ui, sans-serif',
                    'monoFont' => 'ui-monospace, SFMono-Regular, Menlo, monospace',
                    'headingWeight' => '700',
                ],
                'motion' => [
                    'easing' => 'cubic-bezier(0.25, 1, 0.5, 1)',
                    'transition' => 'dissolve',
                    'motif' => 'waves',
                ],
                'sfxProfile' => [
                    'clickFreq' => 500,
                    'whooshDuration' => 0.34,
                    'chimeNotes' => [392.0, 493.88, 587.33],
                ],
                'promptBrief' => 'Warm cream (#faf7f2) organic canvas with serif headings in forest green (#166534), amber (#d97706) highlights, flowing wave motif, and gentle dissolve transitions.',
            ],
            'neon-telemetry' => [
                'id' => 'neon-telemetry',
                'name' => 'Neon Telemetry',
                'category' => 'technical',
                'description' => 'Deep obsidian dashboard style with violet and magenta telemetry pulses for AI and live data.',
                'palette' => [
                    'bg' => '#09090b',
                    'surface' => '#18181b',
                    'primary' => '#a855f7',
                    'secondary' => '#ec4899',
                    'accent' => '#c084fc',
                    'text' => '#fafafa',
                    'muted' => '#a1a1aa',
                ],
                'typography' => [
                    'headingFont' => 'Inter, system-ui, sans-serif',
                    'bodyFont' => 'Inter, system-ui, sans-serif',
                    'monoFont' => 'ui-monospace, SFMono-Regular, Menlo, monospace',
                    'headingWeight' => '800',
                ],
                'motion' => [
                    'easing' => 'cubic-bezier(0.16, 1, 0.3, 1)',
                    'transition' => 'zoom',
                    'motif' => 'particles',
                ],
                'sfxProfile' => [
                    'clickFreq' => 760,
                    'whooshDuration' => 0.22,
                    'chimeNotes' => [523.25, 698.46, 880.0],
                ],
                'promptBrief' => 'Obsidian (#09090b) telemetry canvas with violet (#a855f7) and magenta (#ec4899) accents, animated particle field, and dynamic zoom transitions.',
            ],
            'cobalt-saas' => [
                'id' => 'cobalt-saas',
                'name' => 'Cobalt SaaS',
                'category' => 'saas',
                'description' => 'Crisp royal-blue product showcase aesthetic optimised for walkthroughs and feature launches.',
                'palette' => [
                    'bg' => '#eff6ff',
                    'surface' => '#ffffff',
                    'primary' => '#1d4ed8',
                    'secondary' => '#0ea5e9',
                    'accent' => '#2563eb',
                    'text' => '#0f172a',
                    'muted' => '#475569',
                ],
                'typography' => [
                    'headingFont' => 'Inter, system-ui, sans-serif',
                    'bodyFont' => 'Inter, system-ui, sans-serif',
                    'monoFont' => 'ui-monospace, SFMono-Regular, Menlo, monospace',
                    'headingWeight' => '800',
                ],
                'motion' => [
                    'easing' => 'cubic-bezier(0.22, 1, 0.36, 1)',
                    'transition' => 'slide',
                    'motif' => 'grid',
                ],
                'sfxProfile' => [
                    'clickFreq' => 600,
                    'whooshDuration' => 0.25,
                    'chimeNotes' => [523.25, 659.25, 783.99],
                ],
                'promptBrief' => 'Bright ice-blue (#eff6ff) SaaS product canvas with crisp white cards, royal-blue (#1d4ed8) CTAs, subtle grid backdrop, and smooth slide transitions.',
            ],
        ];
    }

    /**
     * Retrieves a single style preset by ID, falling back to DEFAULT_STYLE if unknown.
     *
     * @param string $styleId
     * @return array<string, mixed>
     */
    public static function get(string $styleId): array
    {
        $styles = self::all();
        return $styles[$styleId] ?? $styles[self::DEFAULT_STYLE];
    }

    /**
     * Checks whether a given style ID exists in the atlas.
     *
     * @param string $styleId
     * @return bool
     */
    public static function has(string $styleId): bool
    {
        return isset(self::all()[$styleId]);
    }

    /**
     * Filters styles by category (e.g. 'saas', 'fintech', 'healthcare', 'editorial', 'technical', 'creative').
     *
     * @param string $category
     * @return array<string, array<string, mixed>>
     */
    public static function byCategory(string $category): array
    {
        $needle = strtolower(trim($category));
        return array_filter(
            self::all(),
            static fn (array $style): bool => ($style['category'] ?? '') === $needle
        );
    }

    /**
     * Returns lightweight summaries suitable for UI pickers and API listings.
     *
     * @return array<int, array{id: string, name: string, category: string, description: string, primary: string, bg: string, transition: string}>
     */
    public static function listSummaries(): array
    {
        $out = [];
        foreach (self::all() as $style) {
            $out[] = [
                'id' => (string)$style['id'],
                'name' => (string)$style['name'],
                'category' => (string)$style['category'],
                'description' => (string)$style['description'],
                'primary' => (string)$style['palette']['primary'],
                'bg' => (string)$style['palette']['bg'],
                'transition' => (string)$style['motion']['transition'],
            ];
        }
        return $out;
    }

    /**
     * Generates a copy-pasteable prompt brief describing the visual style for AI scene authoring.
     *
     * @param string $styleId
     * @return string
     */
    public static function toPromptString(string $styleId): string
    {
        $style = self::get($styleId);
        return sprintf(
            "[Style Atlas: %s (%s)] %s Typography: %s (weight %s). Transition: %s with %s motif.",
            $style['name'],
            $style['id'],
            $style['promptBrief'],
            $style['typography']['headingFont'],
            $style['typography']['headingWeight'],
            $style['motion']['transition'],
            $style['motion']['motif']
        );
    }
}
