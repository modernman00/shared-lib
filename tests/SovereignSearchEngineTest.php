<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\Search\SovereignSearchEngine;
use Src\Search\VectorEngine;

class SovereignSearchEngineTest extends TestCase
{
    public function testVectorCosineSimilarity(): void
    {
        $vecA = [1.0, 0.0, 0.0];
        $vecB = [1.0, 0.0, 0.0];
        $vecC = [0.0, 1.0, 0.0];

        $identical = VectorEngine::cosineSimilarity($vecA, $vecB);
        $this->assertEqualsWithDelta(1.0, $identical, 0.0001);

        $orthogonal = VectorEngine::cosineSimilarity($vecA, $vecC);
        $this->assertEqualsWithDelta(0.0, $orthogonal, 0.0001);
    }

    public function testVectorPackingAndUnpacking(): void
    {
        $original = [0.1234, -0.5678, 0.9999];
        $packed = VectorEngine::packVector($original);
        $unpacked = VectorEngine::unpackVector($packed);

        $this->assertCount(3, $unpacked);
        $this->assertEqualsWithDelta($original[0], $unpacked[0], 0.0001);
        $this->assertEqualsWithDelta($original[1], $unpacked[1], 0.0001);
        $this->assertEqualsWithDelta($original[2], $unpacked[2], 0.0001);
    }

    public function testVectorInMemorySearch(): void
    {
        $engine = new VectorEngine();
        $engine->indexInMemory('doc_party', [0.9, 0.1, 0.0], ['category' => 'party']);
        $engine->indexInMemory('doc_finance', [0.0, 0.1, 0.9], ['category' => 'finance']);

        $query = [0.85, 0.15, 0.0];
        $results = $engine->searchInMemory($query, 1);

        $this->assertCount(1, $results);
        $this->assertSame('doc_party', $results[0]['id']);
        $this->assertGreaterThan(0.9, $results[0]['similarity']);
    }

    public function testBM25SearchAndSnippetHighlighting(): void
    {
        $search = new SovereignSearchEngine();

        $search->indexDocument('doc_1', [
            'title' => 'Party Platform Celebration Guidelines',
            'body' => 'Organise unforgettable live party events with dynamic playlists, Aux roulette, and interactive confetti reactions.'
        ]);

        $search->indexDocument('doc_2', [
            'title' => 'Loan Easy Credit Underwriting Protocol',
            'body' => 'Instant algorithmic underwriting and APR calculation with zero hidden brokerage fees and full FCA compliance.'
        ]);

        $results = $search->search('party roulette', 5);

        $this->assertNotEmpty($results);
        $this->assertSame('doc_1', $results[0]['id']);
        $this->assertStringContainsString('<mark', $results[0]['snippet']);
        $this->assertStringContainsString('roulette', $results[0]['snippet']);
    }

    public function testHybridSearchIntegration(): void
    {
        $vectorEngine = new VectorEngine();
        $search = new SovereignSearchEngine($vectorEngine);

        // Document 1
        $search->indexDocument(
            'item_1',
            ['title' => 'Family Quest Chore Manager', 'body' => 'Coordinate household chores with pinball points.'],
            ['app' => 'FamilyPlatform'],
            [0.8, 0.2, 0.0]
        );

        // Document 2
        $search->indexDocument(
            'item_2',
            ['title' => 'Decision Matrix Dilemma Arena', 'body' => 'Resolve cognitive dilemmas with Monte Carlo slingshots.'],
            ['app' => 'iDecideApp'],
            [0.1, 0.2, 0.9]
        );

        $hybridResults = $search->hybridSearch('chore pinball', [0.75, 0.25, 0.0], 2);

        $this->assertNotEmpty($hybridResults);
        $this->assertSame('item_1', $hybridResults[0]['id']);
        $this->assertArrayHasKey('rrf_score', $hybridResults[0]);
    }

    public function testSecurityXssAndMarkTagCorruptionInHighlightSnippet(): void
    {
        // 1. Document with raw HTML / angle brackets and query words that match <mark class="..."> attributes
        $maliciousDoc = 'Alert: 1 < 2 && <img src=x onerror=alert(1)> mark class bg-amber-200 rounded payload';
        $snippet = SovereignSearchEngine::generateHighlightSnippet($maliciousDoc, ['mark', 'class', '<script>alert(1)</script>']);

        $this->assertStringNotContainsString('<img', $snippet);
        $this->assertStringNotContainsString('<script>', $snippet);
        $this->assertStringContainsString('&lt; 2 &amp;&amp;', $snippet);
        // Ensure <mark> tag itself was not corrupted by recursive 'class' matching
        $this->assertStringNotContainsString('<mark <mark', $snippet);
        $this->assertStringContainsString('<mark class="bg-amber-200 text-slate-900 rounded px-1">mark</mark>', $snippet);
        $this->assertStringContainsString('<mark class="bg-amber-200 text-slate-900 rounded px-1">class</mark>', $snippet);
    }

    public function testVectorEngineRejectsNaNAndInfinityPoisoning(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        VectorEngine::cosineSimilarity([1.0, NAN, 0.5], [1.0, 0.2, 0.5]);
    }

    public function testLocalEmbeddingAndTurnkeySearchCorpus(): void
    {
        $vec1 = VectorEngine::generateLocalEmbedding('Burna Boy Last Last Afrobeats DJ Soundstage', 64);
        $vec2 = VectorEngine::generateLocalEmbedding('Burnaboy LastLast Afrobeat track request', 64);
        $vecUnrelated = VectorEngine::generateLocalEmbedding('HMRC Corporation Tax Self Assessment Return', 64);

        $this->assertCount(64, $vec1);
        $simClose = VectorEngine::cosineSimilarity($vec1, $vec2);
        $simFar = VectorEngine::cosineSimilarity($vec1, $vecUnrelated);
        $this->assertGreaterThan($simFar, $simClose);

        $engine = new SovereignSearchEngine(new VectorEngine());
        $corpus = [
            ['id' => 'trk_1', 'title' => 'Burna Boy - Last Last', 'body' => 'High-energy Afrobeats floor filler for wedding reception.', 'category' => 'Afrobeats'],
            ['id' => 'trk_2', 'title' => 'ABBA - Dancing Queen', 'body' => 'Classic disco celebration anthem for all generations.', 'category' => 'Disco'],
        ];

        $results = $engine->searchCorpus('Burnaboy Afrobeat', $corpus, 5);
        $this->assertNotEmpty($results);
        $this->assertSame('trk_1', $results[0]['id']);
        $this->assertArrayHasKey('rrf_score', $results[0]);
    }
}
