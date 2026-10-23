<?php

declare(strict_types=1);

namespace Src;

/**
 * Machine-Readable Agent Documentation & LLM Specification Helper
 *
 * Implements:
 * - RFC-style /llms.txt generation for internal services, platforms, and APIs
 * - Content-negotiated Markdown (.md) routing for autonomous AI coding agents
 * - Self-documenting prompt capsules for zero-ambiguity agentic integration
 */
final class AgentDocHelper
{
    /**
     * Generate standard llms.txt index content for an application.
     *
     * @param string $appName Application display name
     * @param string $description High-level product summary and capabilities
     * @param string $baseUrl Canonical base URL of the application
     * @param array<string, string> $endpoints Map of endpoint/guide title => relative or absolute markdown URL
     * @return string Valid llms.txt document
     */
    public static function generateLlmsTxt(
        string $appName,
        string $description,
        string $baseUrl,
        array $endpoints
    ): string {
        $cleanBase = rtrim($baseUrl, '/');
        $lines = [
            "# {$appName}",
            '',
            "> {$description}",
            '',
            "Docs: {$cleanBase}/docs",
            "API Specs (Markdown): {$cleanBase}/api.md",
            '',
            '## Endpoints & Capabilities',
            '',
        ];

        foreach ($endpoints as $title => $path) {
            $fullUrl = str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
                ? $path
                : "{$cleanBase}/" . ltrim($path, '/');
            $lines[] = "- [{$title}]({$fullUrl})";
        }

        $lines[] = '';
        $lines[] = '## AI Agent Instruction';
        $lines[] = 'Append `.md` to documentation routes to fetch plain markdown instead of HTML.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Determine if an incoming HTTP request prefers plain markdown over HTML.
     *
     * @param array<string, mixed>|null $server Optional $_SERVER superglobal replacement
     * @return bool True if client requested .md or text/markdown
     */
    public static function prefersMarkdown(?array $server = null): bool
    {
        $server = $server ?? $_SERVER;
        $uri = (string)($server['REQUEST_URI'] ?? '');
        $accept = (string)($server['HTTP_ACCEPT'] ?? '');

        $path = (string)(parse_url($uri, PHP_URL_PATH) ?? '');
        if (str_ends_with(strtolower($path), '.md')) {
            return true;
        }

        if (str_contains(strtolower($accept), 'text/markdown')) {
            return true;
        }

        return false;
    }

    /**
     * Generate a structured system prompt capsule for an agent integrating this API.
     *
     * @param string $appName
     * @param string $baseUrl
     * @param array<string, string> $rules List of strict behavioral rules for the agent
     * @return string
     */
    public static function generateAgentPrompt(string $appName, string $baseUrl, array $rules = []): string
    {
        $cleanBase = rtrim($baseUrl, '/');
        $lines = [
            "You are integrating {$appName}.",
            '',
            "Read the official Markdown documentation first:",
            "- {$cleanBase}/llms.txt",
            "- {$cleanBase}/docs/overview.md",
            "- {$cleanBase}/docs/api.md",
            '',
            'Rules:',
        ];

        if (empty($rules)) {
            $rules = [
                'Detect client environment and use modern native APIs without unnecessary dependencies.',
                'Work in development mode unless explicitly instructed to target production.',
                'Never expose server API keys or database credentials in client code or browser bundles.',
                'Enforce strict error handling, input validation, and bounded timeouts on all network calls.',
            ];
        }

        $idx = 1;
        foreach ($rules as $rule) {
            $lines[] = "{$idx}. {$rule}";
            $idx++;
        }

        return implode("\n", $lines);
    }
}
