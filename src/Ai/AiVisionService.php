<?php

declare(strict_types=1);

namespace Src\Ai;

/**
 * AI Multimodal Vision Service
 *
 * Employs open-weights Vision-Language models (e.g. Qwen2-VL, Llama-3.2-Vision)
 * on Hugging Face serverless inference to parse real-world documents (bar receipts, restaurant bills)
 * and scenes (fridge inventory for meal planning).
 *
 * Gatewatcher & SecOps Protections:
 * - Rejects files exceeding 10MB or 4096x4096px (preventing memory decompression bombs).
 * - Enforces strict JSON extraction with deterministic schema fallbacks.
 * - Sanitizes all parsed text labels against HTML/XSS injection.
 */
class AiVisionService
{
    private HuggingFaceInferenceGateway $gateway;

    private const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024; // 10MB
    private const MAX_DIMENSION_PX = 4096;

    public function __construct(?HuggingFaceInferenceGateway $gateway = null)
    {
        $this->gateway = $gateway ?? new HuggingFaceInferenceGateway();
    }

    /**
     * Parse a restaurant bill, bar tab, or invoice photo into itemized records for splitting.
     *
     * @param string $imageFilePath Absolute path to receipt photo
     * @param string $model Default: Qwen/Qwen2-VL-7B-Instruct
     * @return array{
     *     success: bool,
     *     items: array<int, array{name: string, price: float, quantity: int}>,
     *     subtotal: float,
     *     tax: float,
     *     total: float,
     *     currency: string,
     *     error?: string
     * }
     */
    public function parseReceipt(
        string $imageFilePath,
        string $model = 'Qwen/Qwen2-VL-7B-Instruct'
    ): array {
        $validation = $this->validateImageFile($imageFilePath);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'items' => [],
                'subtotal' => 0.0,
                'tax' => 0.0,
                'total' => 0.0,
                'currency' => 'GBP',
                'error' => $validation['error'],
            ];
        }

        $systemPrompt = 'You are an expert OCR receipt parsing engine. Analyze the provided receipt photo and extract all purchased line items with their quantity and price. You must output ONLY a valid JSON object without commentary, conforming to this exact schema: {"items": [{"name": "Item Description", "price": 0.00, "quantity": 1}], "subtotal": 0.00, "tax": 0.00, "total": 0.00, "currency": "GBP"}';

        $prompt = 'Extract the itemized line items, subtotal, tax, total amount, and currency from this receipt. Return ONLY the raw JSON object.';

        $rawResponse = $this->gateway->analyzeImageWithPrompt($imageFilePath, $prompt, $model, $systemPrompt);

        if ($rawResponse === null || trim($rawResponse) === '') {
            return [
                'success' => false,
                'items' => [],
                'subtotal' => 0.0,
                'tax' => 0.0,
                'total' => 0.0,
                'currency' => 'GBP',
                'error' => 'Vision model did not return a response or timed out.',
            ];
        }

        $parsed = $this->extractJson($rawResponse);
        if (!is_array($parsed)) {
            return [
                'success' => false,
                'items' => [],
                'subtotal' => 0.0,
                'tax' => 0.0,
                'total' => 0.0,
                'currency' => 'GBP',
                'error' => 'Failed to parse structured JSON from vision model.',
            ];
        }

        $items = [];
        if (isset($parsed['items']) && is_array($parsed['items'])) {
            foreach ($parsed['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $name = htmlspecialchars(trim((string)($item['name'] ?? 'Item')), ENT_QUOTES, 'UTF-8');
                $price = (float)($item['price'] ?? 0.0);
                $quantity = max(1, (int)($item['quantity'] ?? 1));
                $items[] = [
                    'name' => $name,
                    'price' => round($price, 2),
                    'quantity' => $quantity,
                ];
            }
        }

        $total = isset($parsed['total']) ? round((float)$parsed['total'], 2) : 0.0;
        $subtotal = isset($parsed['subtotal']) ? round((float)$parsed['subtotal'], 2) : $total;
        $tax = isset($parsed['tax']) ? round((float)$parsed['tax'], 2) : 0.0;
        $currency = strtoupper(trim((string)($parsed['currency'] ?? 'GBP')));

        // If items were extracted but total is zero, sum them up
        if ($total <= 0.0 && count($items) > 0) {
            foreach ($items as $it) {
                $total += $it['price'] * $it['quantity'];
            }
            $subtotal = $total;
        }

        return [
            'success' => true,
            'items' => $items,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'currency' => $currency ?: 'GBP',
        ];
    }

    /**
     * Inspect a photo of a fridge or pantry to detect ingredients and propose quick recipes.
     *
     * @param string $imageFilePath Absolute path to fridge/pantry photo
     * @param string $model
     * @return array{
     *     success: bool,
     *     detected_ingredients: array<int, string>,
     *     suggested_recipes: array<int, array{title: string, cooking_time_minutes: int, instructions: array<int, string>}>,
     *     error?: string
     * }
     */
    public function inspectFridge(
        string $imageFilePath,
        string $model = 'Qwen/Qwen2-VL-7B-Instruct'
    ): array {
        $validation = $this->validateImageFile($imageFilePath);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'detected_ingredients' => [],
                'suggested_recipes' => [],
                'error' => $validation['error'],
            ];
        }

        $systemPrompt = 'You are a culinary vision assistant. Analyze the photo of the fridge or pantry, identify visible food items and ingredients, and propose 2 quick, family-friendly meals. You must output ONLY a valid JSON object conforming to this schema: {"detected_ingredients": ["item1", "item2"], "suggested_recipes": [{"title": "Recipe Name", "cooking_time_minutes": 15, "instructions": ["Step 1", "Step 2"]}]}';

        $prompt = 'Identify the food items in this photo and suggest 2 fast meals that can be cooked with them. Return ONLY raw JSON.';

        $rawResponse = $this->gateway->analyzeImageWithPrompt($imageFilePath, $prompt, $model, $systemPrompt);

        if ($rawResponse === null || trim($rawResponse) === '') {
            return [
                'success' => false,
                'detected_ingredients' => [],
                'suggested_recipes' => [],
                'error' => 'Vision model did not return a response or timed out.',
            ];
        }

        $parsed = $this->extractJson($rawResponse);
        if (!is_array($parsed)) {
            return [
                'success' => false,
                'detected_ingredients' => [],
                'suggested_recipes' => [],
                'error' => 'Failed to parse structured JSON from vision model.',
            ];
        }

        $ingredients = [];
        if (isset($parsed['detected_ingredients']) && is_array($parsed['detected_ingredients'])) {
            foreach ($parsed['detected_ingredients'] as $ing) {
                if (is_string($ing) && trim($ing) !== '') {
                    $ingredients[] = htmlspecialchars(trim($ing), ENT_QUOTES, 'UTF-8');
                }
            }
        }

        $recipes = [];
        if (isset($parsed['suggested_recipes']) && is_array($parsed['suggested_recipes'])) {
            foreach ($parsed['suggested_recipes'] as $rec) {
                if (!is_array($rec)) {
                    continue;
                }
                $title = htmlspecialchars(trim((string)($rec['title'] ?? 'Quick Meal')), ENT_QUOTES, 'UTF-8');
                $time = max(5, (int)($rec['cooking_time_minutes'] ?? 15));
                $instructions = [];
                if (isset($rec['instructions']) && is_array($rec['instructions'])) {
                    foreach ($rec['instructions'] as $step) {
                        if (is_string($step) && trim($step) !== '') {
                            $instructions[] = htmlspecialchars(trim($step), ENT_QUOTES, 'UTF-8');
                        }
                    }
                }
                $recipes[] = [
                    'title' => $title,
                    'cooking_time_minutes' => $time,
                    'instructions' => $instructions,
                ];
            }
        }

        return [
            'success' => true,
            'detected_ingredients' => $ingredients,
            'suggested_recipes' => $recipes,
        ];
    }

    /**
     * SecOps Defensive Gate: Image sanity validation against decompression bombs.
     *
     * @param string $path
     * @return array{valid: bool, error: string}
     */
    private function validateImageFile(string $path): array
    {
        if (!file_exists($path)) {
            return ['valid' => false, 'error' => 'File does not exist.'];
        }

        $size = filesize($path);
        if ($size === false || $size === 0) {
            return ['valid' => false, 'error' => 'File is empty.'];
        }

        if ($size > self::MAX_FILE_SIZE_BYTES) {
            return ['valid' => false, 'error' => 'File size exceeds maximum allowable limit of 10MB.'];
        }

        // Check image dimensions without loading entire file into memory
        $imageInfo = @getimagesize($path);
        if ($imageInfo === false) {
            return ['valid' => false, 'error' => 'File is not a valid image.'];
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];
        if ($width > self::MAX_DIMENSION_PX || $height > self::MAX_DIMENSION_PX) {
            return ['valid' => false, 'error' => 'Image dimensions exceed maximum allowable 4096x4096px limit.'];
        }

        return ['valid' => true, 'error' => ''];
    }

    /**
     * Helper to extract clean JSON object from LLM response which may contain markdown code fencing.
     *
     * @param string $raw
     * @return array<string, mixed>|null
     */
    private function extractJson(string $raw): ?array
    {
        $clean = trim($raw);

        // Strip ```json and ``` code blocks if present
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $clean, $matches)) {
            $clean = trim($matches[1]);
        } elseif (preg_match('/(\{.*\})/s', $clean, $matches)) {
            $clean = trim($matches[1]);
        }

        $decoded = json_decode($clean, true);
        return is_array($decoded) ? $decoded : null;
    }
}
