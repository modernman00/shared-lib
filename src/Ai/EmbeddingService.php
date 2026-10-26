<?php

declare(strict_types=1);

namespace Src\Ai;

/**
 * EmbeddingService — Fast Dense Vector Embeddings & In-Memory Semantic Ranking
 *
 * Implements:
 * - 384-dimensional vector embeddings via sentence-transformers/all-MiniLM-L6-v2
 * - High-speed in-memory Cosine Similarity ranking
 * - Zero external vector database required (Pinecone-free architecture)
 *
 * Applicable to:
 * - FamilyPlatform: Natural language family archive & memory retrieval
 * - ExecMind: Board minutes & strategy semantic lookup
 * - PartyPlatform: Guest interest & music catalog semantic matching
 */
final class EmbeddingService
{
    private HuggingFaceInferenceGateway $gateway;
    private string $model;

    public function __construct(?HuggingFaceInferenceGateway $gateway = null, ?string $model = null)
    {
        $this->gateway = $gateway ?? new HuggingFaceInferenceGateway();
        $this->model = $model ?? 'sentence-transformers/all-MiniLM-L6-v2';
    }

    /**
     * Generate dense vector embedding for single text or batch of texts.
     *
     * @param string|array<int, string> $input
     * @return array<int, float>|array<int, array<int, float>>|null
     */
    public function embed(string|array $input): ?array
    {
        if (is_string($input) && trim($input) === '') {
            return null;
        }

        if (is_array($input) && empty($input)) {
            return null;
        }

        try {
            return $this->gateway->generateEmbeddings($input, $this->model);
        } catch (\Throwable $th) {
            error_log('[EmbeddingService] Embedding error: ' . $th->getMessage());
            return null;
        }
    }

    /**
     * Calculate Cosine Similarity between two dense floating-point vectors (-1.0 to 1.0).
     *
     * @param array<int, float> $vecA
     * @param array<int, float> $vecB
     * @return float
     */
    public static function cosineSimilarity(array $vecA, array $vecB): float
    {
        $count = count($vecA);
        if ($count === 0 || $count !== count($vecB)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $a = (float)$vecA[$i];
            $b = (float)$vecB[$i];
            $dotProduct += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Rank a collection of database items by semantic similarity to a query vector.
     *
     * @param array<int, float> $queryVector
     * @param array<int, array{id: string|int, vector: array<int, float>|string, data?: mixed}> $items
     * @param int $topK Maximum number of results to return
     * @return array<int, array{id: string|int, similarity: float, data: mixed}> Ranked top items
     */
    public static function rankBySimilarity(array $queryVector, array $items, int $topK = 5): array
    {
        $topK = max(1, min($topK, 100));
        $scored = [];

        foreach ($items as $item) {
            $rawVec = $item['vector'];
            /** @var array<int, float> $vec */
            $vec = is_string($rawVec) ? (json_decode($rawVec, true) ?: []) : $rawVec;

            if (!is_array($vec) || empty($vec)) {
                continue;
            }

            $score = self::cosineSimilarity($queryVector, $vec);
            $scored[] = [
                'id'         => $item['id'],
                'similarity' => round($score, 5),
                'data'       => $item['data'] ?? null,
            ];
        }

        // Sort descending by similarity
        usort($scored, static fn(array $a, array $b): int => $b['similarity'] <=> $a['similarity']);

        return array_slice($scored, 0, $topK);
    }
}
