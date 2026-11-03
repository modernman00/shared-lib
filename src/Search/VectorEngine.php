<?php

declare(strict_types=1);

namespace Src\Search;

use InvalidArgumentException;
use PDO;
use Src\Db;

/**
 * VectorEngine
 *
 * Sovereign, zero-dependency high-dimensional vector search and cosine similarity engine
 * engineered by the Advanced Technology Group (ATG) under Olutobi (Deloitte) and Victor (CTO).
 *
 * Replaces hosted Pinecone, Weaviate, and Milvus subscriptions with lightweight,
 * sub-millisecond in-process vector retrieval and binary float persistence.
 */
class VectorEngine
{
    private ?PDO $pdo = null;
    /** @var array<string|int, array{vector: array<float>, metadata: array<string, mixed>}> */
    private array $inMemoryIndex = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    private function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = Db::connect2();
        }
        return $this->pdo;
    }

    private const MAX_VECTOR_DIMENSIONS = 4096;

    /**
     * Validates and normalises a float vector against NaN/Infinity poisoning and dimension bombs
     *
     * @param array<int|string, mixed> $vector
     * @return array<float>
     */
    public static function sanitizeVector(array $vector): array
    {
        $count = count($vector);
        if ($count === 0 || $count > self::MAX_VECTOR_DIMENSIONS) {
            throw new InvalidArgumentException('Vector dimensions must be between 1 and ' . self::MAX_VECTOR_DIMENSIONS . '.');
        }

        $clean = [];
        foreach ($vector as $val) {
            if (!is_int($val) && !is_float($val)) {
                throw new InvalidArgumentException('Vector elements must be numeric.');
            }
            $f = (float) $val;
            if (!is_finite($f)) {
                throw new InvalidArgumentException('Vector elements must be finite numbers (NaN/INF rejected).');
            }
            $clean[] = $f;
        }

        return $clean;
    }

    /**
     * Calculate cosine similarity between two float vectors
     *
     * @param array<float> $vecA
     * @param array<float> $vecB
     * @return float Cosine similarity score between -1.0 and 1.0 (typically 0.0 to 1.0)
     */
    public static function cosineSimilarity(array $vecA, array $vecB): float
    {
        $countA = count($vecA);
        if ($countA === 0 || $countA > self::MAX_VECTOR_DIMENSIONS || $countA !== count($vecB)) {
            throw new InvalidArgumentException('Vector dimensions must be non-zero, bounded, and identical.');
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $countA; $i++) {
            $a = (float)$vecA[$i];
            $b = (float)$vecB[$i];
            if (!is_finite($a) || !is_finite($b)) {
                throw new InvalidArgumentException('Vector contains non-finite (NaN or Infinite) float values.');
            }
            $dotProduct += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        $sim = $dotProduct / (sqrt($normA) * sqrt($normB));
        return is_finite($sim) ? max(-1.0, min(1.0, $sim)) : 0.0;
    }

    /**
     * Packs an array of floats into a binary string for compact storage
     *
     * @param array<float> $vector
     * @return string
     */
    public static function packVector(array $vector): string
    {
        $clean = self::sanitizeVector($vector);
        return pack('f*', ...$clean);
    }

    /**
     * Unpacks a binary string back into an array of floats
     *
     * @param string $binary
     * @return array<float>
     */
    public static function unpackVector(string $binary): array
    {
        if ($binary === '' || (strlen($binary) % 4) !== 0 || strlen($binary) > (self::MAX_VECTOR_DIMENSIONS * 4)) {
            return [];
        }
        $unpacked = unpack('f*', $binary);
        if ($unpacked === false) {
            return [];
        }
        $values = array_values($unpacked);
        foreach ($values as $v) {
            if (!is_finite((float) $v)) {
                return [];
            }
        }
        return $values;
    }

    /**
     * Index a vector document in memory
     *
     * @param string|int $id
     * @param array<float> $vector
     * @param array<string, mixed> $metadata
     */
    public function indexInMemory(string|int $id, array $vector, array $metadata = []): void
    {
        $this->inMemoryIndex[$id] = [
            'vector' => self::sanitizeVector($vector),
            'metadata' => $metadata,
        ];
    }

    /**
     * Search in-memory index for the K most similar vectors
     *
     * @param array<float> $queryVector
     * @param int $k Top results to return
     * @param float $minSimilarity Minimum similarity threshold (e.g. 0.6)
     * @return array<int, array{id: string|int, similarity: float, metadata: array<string, mixed>}>
     */
    public function searchInMemory(array $queryVector, int $k = 10, float $minSimilarity = 0.0): array
    {
        $cleanQuery = self::sanitizeVector($queryVector);
        $k = max(1, min($k, 200));
        $queryDim = count($cleanQuery);
        $results = [];

        foreach ($this->inMemoryIndex as $id => $doc) {
            if (count($doc['vector']) !== $queryDim) {
                continue;
            }
            $sim = self::cosineSimilarity($cleanQuery, $doc['vector']);
            if ($sim >= $minSimilarity) {
                $results[] = [
                    'id' => $id,
                    'similarity' => round($sim, 6),
                    'metadata' => $doc['metadata'],
                ];
            }
        }

        // Sort descending by similarity score
        usort($results, static fn(array $a, array $b): int => $b['similarity'] <=> $a['similarity']);

        return array_slice($results, 0, $k);
    }

    /**
     * Ensures vector storage schema exists in SQL database
     */
    public function ensureSchema(): void
    {
        $pdo = $this->getPdo();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS sovereign_vector_index (
                    id TEXT PRIMARY KEY,
                    collection TEXT NOT NULL,
                    vector_blob BLOB NOT NULL,
                    metadata_json TEXT NOT NULL,
                    created_at INTEGER NOT NULL
                );
                CREATE INDEX IF NOT EXISTS idx_svi_collection ON sovereign_vector_index (collection);
            ");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS sovereign_vector_index (
                    id VARCHAR(191) PRIMARY KEY,
                    collection VARCHAR(191) NOT NULL,
                    vector_blob LONGBLOB NOT NULL,
                    metadata_json LONGTEXT NOT NULL,
                    created_at BIGINT UNSIGNED NOT NULL,
                    INDEX idx_svi_collection (collection)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        }
    }

    /**
     * Persist a vector document to the database
     *
     * @param string $id
     * @param string $collection Collection or tenant identifier
     * @param array<float> $vector
     * @param array<string, mixed> $metadata
     */
    public function persist(string $id, string $collection, array $vector, array $metadata = []): void
    {
        $this->ensureSchema();
        $blob = self::packVector($vector);
        $metaJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $now = time();

        $pdo = $this->getPdo();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $sql = "INSERT INTO sovereign_vector_index (id, collection, vector_blob, metadata_json, created_at)
                    VALUES (:id, :collection, :vector_blob, :metadata_json, :created_at)
                    ON CONFLICT(id) DO UPDATE SET collection = :collection, vector_blob = :vector_blob, metadata_json = :metadata_json";
        } else {
            $sql = "INSERT INTO sovereign_vector_index (id, collection, vector_blob, metadata_json, created_at)
                    VALUES (:id, :collection, :vector_blob, :metadata_json, :created_at)
                    ON DUPLICATE KEY UPDATE collection = VALUES(collection), vector_blob = VALUES(vector_blob), metadata_json = VALUES(metadata_json)";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':collection' => $collection,
            ':vector_blob' => $blob,
            ':metadata_json' => $metaJson,
            ':created_at' => $now,
        ]);
    }

    /**
     * Search database vectors by cosine similarity
     *
     * @param string $collection
     * @param array<float> $queryVector
     * @param int $k
     * @param float $minSimilarity
     * @return array<int, array{id: string, similarity: float, metadata: array<string, mixed>}>
     */
    public function searchDatabase(string $collection, array $queryVector, int $k = 10, float $minSimilarity = 0.0): array
    {
        $cleanQuery = self::sanitizeVector($queryVector);
        $queryDim = count($cleanQuery);
        $k = max(1, min($k, 200));

        $this->ensureSchema();
        $stmt = $this->getPdo()->prepare("SELECT id, vector_blob, metadata_json FROM sovereign_vector_index WHERE collection = :collection LIMIT 5000");
        $stmt->execute([':collection' => $collection]);

        $candidates = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $docVector = self::unpackVector((string)$row['vector_blob']);
            if (empty($docVector) || count($docVector) !== $queryDim) {
                continue;
            }

            $sim = self::cosineSimilarity($cleanQuery, $docVector);
            if ($sim >= $minSimilarity) {
                $meta = json_decode((string)$row['metadata_json'], true);
                $candidates[] = [
                    'id' => (string)$row['id'],
                    'similarity' => round($sim, 6),
                    'metadata' => is_array($meta) ? $meta : [],
                ];
            }
        }

        usort($candidates, static fn(array $a, array $b): int => $b['similarity'] <=> $a['similarity']);
        return array_slice($candidates, 0, $k);
    }
}
