<?php

declare(strict_types=1);

namespace Src\Search;

use InvalidArgumentException;
use PDO;
use Src\Db;

/**
 * SovereignSearchEngine
 *
 * Elite, self-hosted search and retrieval engine engineered by the Advanced Technology Group (ATG)
 * under Olutobi (Deloitte) and Victor (CTO).
 *
 * Replaces commercial Algolia, Elasticsearch, and Meilisearch subscriptions with high-speed
 * BM25 inverted index tokenization and hybrid vector search via Reciprocal Rank Fusion (RRF).
 */
class SovereignSearchEngine
{
    private const DEFAULT_K1 = 1.2;
    private const DEFAULT_B = 0.75;

    /** @var array<string, true> */
    private static array $stopWords = [
        'a' => true, 'about' => true, 'above' => true, 'after' => true, 'again' => true,
        'against' => true, 'all' => true, 'am' => true, 'an' => true, 'and' => true,
        'any' => true, 'are' => true, 'as' => true, 'at' => true, 'be' => true,
        'because' => true, 'been' => true, 'before' => true, 'being' => true, 'below' => true,
        'between' => true, 'both' => true, 'but' => true, 'by' => true, 'could' => true,
        'did' => true, 'do' => true, 'does' => true, 'doing' => true, 'down' => true,
        'during' => true, 'each' => true, 'few' => true, 'for' => true, 'from' => true,
        'further' => true, 'had' => true, 'has' => true, 'have' => true, 'having' => true,
        'he' => true, 'her' => true, 'here' => true, 'hers' => true, 'herself' => true,
        'him' => true, 'himself' => true, 'his' => true, 'how' => true, 'i' => true,
        'if' => true, 'in' => true, 'into' => true, 'is' => true, 'it' => true,
        'its' => true, 'itself' => true, 'me' => true, 'more' => true, 'most' => true,
        'my' => true, 'myself' => true, 'no' => true, 'nor' => true, 'not' => true,
        'of' => true, 'off' => true, 'on' => true, 'once' => true, 'only' => true,
        'or' => true, 'other' => true, 'ought' => true, 'our' => true, 'ours' => true,
        'ourselves' => true, 'out' => true, 'over' => true, 'own' => true, 'same' => true,
        'she' => true, 'should' => true, 'so' => true, 'some' => true, 'such' => true,
        'than' => true, 'that' => true, 'the' => true, 'their' => true, 'theirs' => true,
        'them' => true, 'themselves' => true, 'then' => true, 'there' => true, 'these' => true,
        'they' => true, 'this' => true, 'those' => true, 'through' => true, 'to' => true,
        'too' => true, 'under' => true, 'until' => true, 'up' => true, 'very' => true,
        'was' => true, 'we' => true, 'were' => true, 'what' => true, 'when' => true,
        'where' => true, 'which' => true, 'while' => true, 'who' => true, 'whom' => true,
        'why' => true, 'with' => true, 'would' => true, 'you' => true, 'your' => true,
        'yours' => true, 'yourself' => true, 'yourselves' => true
    ];

    /** @var array<string|int, array{fields: array<string, string>, length: int, metadata: array<string, mixed>}> */
    private array $documents = [];
    /** @var array<string, array<string|int, int>> Term -> DocId -> Term Frequency */
    private array $invertedIndex = [];
    /** @var array<string, int> Term -> Document Frequency */
    private array $docFrequencies = [];
    private int $totalDocLength = 0;
    private ?VectorEngine $vectorEngine;

    public function __construct(?VectorEngine $vectorEngine = null)
    {
        $this->vectorEngine = $vectorEngine;
    }

    /**
     * Tokenizes a text into cleaned, stemmed lowercase word tokens
     *
     * @param string $text
     * @return array<string>
     */
    public static function tokenize(string $text): array
    {
        // Cap input length to prevent CPU/memory exhaustion from malicious/AI payload floods
        if (strlen($text) > 262144) {
            $text = mb_substr($text, 0, 65536, 'UTF-8');
        }
        $cleaned = mb_strtolower(strip_tags($text), 'UTF-8');
        // Replace non-alphanumeric with spaces
        $cleaned = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $cleaned) ?? '';
        $rawWords = preg_split('/\s+/u', $cleaned, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = [];
        foreach ($rawWords as $word) {
            $len = mb_strlen($word);
            if ($len < 2 || $len > 64 || isset(self::$stopWords[$word])) {
                continue;
            }
            // Basic English suffix stripping
            if ($len > 4 && str_ends_with($word, 'ing')) {
                $word = substr($word, 0, -3);
            } elseif ($len > 3 && str_ends_with($word, 'ed')) {
                $word = substr($word, 0, -2);
            } elseif ($len > 3 && str_ends_with($word, 'es')) {
                $word = substr($word, 0, -2);
            } elseif ($len > 3 && str_ends_with($word, 's')) {
                $word = substr($word, 0, -1);
            }
            $tokens[] = $word;
        }

        return $tokens;
    }

    /**
     * Add a document to the index
     *
     * @param string|int $id
     * @param array<string, string> $fields Document fields (e.g. ['title' => '...', 'body' => '...'])
     * @param array<string, mixed> $metadata
     * @param array<float>|null $embedding Optional vector embedding for hybrid search
     */
    public function indexDocument(string|int $id, array $fields, array $metadata = [], ?array $embedding = null): void
    {
        $combinedText = implode(' ', $fields);
        $tokens = self::tokenize($combinedText);
        $docLength = count($tokens);

        // Remove old document if re-indexing
        if (isset($this->documents[$id])) {
            $this->removeDocument($id);
        }

        $termCounts = array_count_values($tokens);

        foreach ($termCounts as $term => $count) {
            if (!isset($this->invertedIndex[$term])) {
                $this->invertedIndex[$term] = [];
            }
            $this->invertedIndex[$term][$id] = $count;
            $this->docFrequencies[$term] = ($this->docFrequencies[$term] ?? 0) + 1;
        }

        $this->documents[$id] = [
            'fields' => $fields,
            'length' => $docLength,
            'metadata' => $metadata,
        ];

        $this->totalDocLength += $docLength;

        if ($embedding !== null && $this->vectorEngine !== null) {
            $this->vectorEngine->indexInMemory($id, $embedding, $metadata);
        }
    }

    /**
     * Remove a document from the index
     */
    public function removeDocument(string|int $id): void
    {
        if (!isset($this->documents[$id])) {
            return;
        }

        $docLength = $this->documents[$id]['length'];
        $this->totalDocLength -= $docLength;

        foreach ($this->invertedIndex as $term => $postings) {
            if (isset($postings[$id])) {
                unset($this->invertedIndex[$term][$id]);
                $this->docFrequencies[$term]--;
                if ($this->docFrequencies[$term] <= 0) {
                    unset($this->docFrequencies[$term], $this->invertedIndex[$term]);
                }
            }
        }

        unset($this->documents[$id]);
    }

    /**
     * BM25 search over indexed documents
     *
     * @param string $query Query string
     * @param int $limit Maximum results
     * @param float $k1 Term frequency saturation parameter
     * @param float $b Document length normalization parameter
     * @return array<int, array{id: string|int, score: float, snippet: string, metadata: array<string, mixed>}>
     */
    public function search(
        string $query,
        int $limit = 10,
        float $k1 = self::DEFAULT_K1,
        float $b = self::DEFAULT_B
    ): array {
        $limit = max(1, min($limit, 100));
        $k1 = is_finite($k1) && $k1 >= 0.0 ? min($k1, 10.0) : self::DEFAULT_K1;
        $b = is_finite($b) && $b >= 0.0 && $b <= 1.0 ? $b : self::DEFAULT_B;

        // Deduplicate & bound query tokens to max 64 terms to neutralize AI token-flood DoS
        $queryTokens = array_slice(array_values(array_unique(self::tokenize(mb_substr($query, 0, 1024, 'UTF-8')))), 0, 64);
        if (empty($queryTokens) || empty($this->documents)) {
            return [];
        }

        $totalDocs = count($this->documents);
        $avgDocLength = $this->totalDocLength / max(1, $totalDocs);
        $scores = [];

        foreach ($queryTokens as $term) {
            if (!isset($this->invertedIndex[$term])) {
                continue;
            }

            $docFreq = $this->docFrequencies[$term];
            // Robertson-Spärck Jones IDF
            $idf = log(1.0 + ($totalDocs - $docFreq + 0.5) / ($docFreq + 0.5));
            if ($idf <= 0.0) {
                $idf = 0.01;
            }

            foreach ($this->invertedIndex[$term] as $docId => $tf) {
                $docLen = $this->documents[$docId]['length'];
                $tfComponent = ($tf * ($k1 + 1.0)) / ($tf + $k1 * (1.0 - $b + $b * ($docLen / max(1.0, $avgDocLength))));
                $scores[$docId] = ($scores[$docId] ?? 0.0) + ($idf * $tfComponent);
            }
        }

        if (empty($scores)) {
            return [];
        }

        arsort($scores, SORT_NUMERIC);

        $results = [];
        $queryWords = preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach (array_slice($scores, 0, $limit, true) as $id => $score) {
            $doc = $this->documents[$id];
            $body = $doc['fields']['body'] ?? $doc['fields']['description'] ?? implode(' - ', $doc['fields']);

            $results[] = [
                'id' => $id,
                'score' => round($score, 4),
                'snippet' => self::generateHighlightSnippet($body, $queryWords),
                'metadata' => $doc['metadata'],
            ];
        }

        return $results;
    }

    /**
     * Hybrid Search: Reciprocal Rank Fusion (RRF) combining BM25 and Vector Search
     *
     * @param string $query
     * @param array<float> $queryVector
     * @param int $limit
     * @param float $lexicalWeight
     * @param float $vectorWeight
     * @return array<int, array{id: string|int, rrf_score: float, snippet: string, metadata: array<string, mixed>}>
     */
    public function hybridSearch(
        string $query,
        array $queryVector,
        int $limit = 10,
        float $lexicalWeight = 1.0,
        float $vectorWeight = 1.0
    ): array {
        if ($this->vectorEngine === null) {
            throw new InvalidArgumentException('VectorEngine must be provided to execute hybrid search.');
        }

        $bm25Results = $this->search($query, $limit * 2);
        $vectorResults = $this->vectorEngine->searchInMemory($queryVector, $limit * 2);

        $rrfScores = [];
        $k = 60.0;

        foreach ($bm25Results as $rank => $item) {
            $id = $item['id'];
            $rrfScores[$id] = ($rrfScores[$id] ?? 0.0) + ($lexicalWeight / ($k + $rank + 1));
        }

        foreach ($vectorResults as $rank => $item) {
            $id = $item['id'];
            $rrfScores[$id] = ($rrfScores[$id] ?? 0.0) + ($vectorWeight / ($k + $rank + 1));
        }

        arsort($rrfScores, SORT_NUMERIC);

        $results = [];
        $queryWords = preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach (array_slice($rrfScores, 0, $limit, true) as $id => $score) {
            $meta = $this->documents[$id]['metadata'] ?? [];
            $body = $this->documents[$id]['fields']['body'] ?? implode(' - ', $this->documents[$id]['fields'] ?? []);

            $results[] = [
                'id' => $id,
                'rrf_score' => round($score, 5),
                'snippet' => self::generateHighlightSnippet($body, $queryWords),
                'metadata' => $meta,
            ];
        }

        return $results;
    }

    /**
     * Generate highlighted snippet around query matches
     *
     * @param string $text
     * @param array<string> $queryWords
     * @param int $maxLength
     * @return string
     */
    public static function generateHighlightSnippet(string $text, array $queryWords, int $maxLength = 160): string
    {
        $maxLength = max(20, min($maxLength, 1000));
        $stripped = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Sanitize and deduplicate query words: keep only clean alphanumeric/Unicode word tokens
        $cleanWords = [];
        foreach ($queryWords as $word) {
            $cleanedWord = trim((string) (preg_replace('/[^\p{L}\p{N}_-]+/u', '', (string) $word) ?? ''));
            if (mb_strlen($cleanedWord, 'UTF-8') >= 2 && count($cleanWords) < 16) {
                $cleanWords[mb_strtolower($cleanedWord, 'UTF-8')] = $cleanedWord;
            }
        }
        $cleanWords = array_values($cleanWords);

        if (empty($cleanWords)) {
            $rawSlice = mb_substr($stripped, 0, $maxLength, 'UTF-8') . (mb_strlen($stripped, 'UTF-8') > $maxLength ? '...' : '');
            return htmlspecialchars($rawSlice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        // Find earliest match position on raw plain text
        $firstPos = false;
        foreach ($cleanWords as $word) {
            $pos = mb_stripos($stripped, $word, 0, 'UTF-8');
            if ($pos !== false && ($firstPos === false || $pos < $firstPos)) {
                $firstPos = $pos;
            }
        }

        $start = 0;
        if ($firstPos !== false && $firstPos > 40) {
            $start = $firstPos - 40;
        }

        $slice = mb_substr($stripped, $start, $maxLength, 'UTF-8');
        if ($start > 0) {
            $slice = '...' . $slice;
        }
        if (mb_strlen($stripped, 'UTF-8') > ($start + $maxLength)) {
            $slice .= '...';
        }

        // HTML-escape the entire plain-text slice BEFORE applying <mark> tags to prevent stored/reflected XSS
        $escapedSlice = htmlspecialchars($slice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Single-pass alternation regex prevents recursive matching inside <mark class="..."> attributes
        usort($cleanWords, static fn(string $a, string $b): int => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));
        $quotedTerms = array_map(
            static fn(string $w): string => preg_quote(htmlspecialchars($w, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), '/'),
            $cleanWords
        );
        $pattern = '/(' . implode('|', $quotedTerms) . ')/iu';

        return preg_replace_callback(
            $pattern,
            static fn(array $matches): string => '<mark class="bg-amber-200 text-slate-900 rounded px-1">' . $matches[0] . '</mark>',
            $escapedSlice
        ) ?? $escapedSlice;
    }
}
