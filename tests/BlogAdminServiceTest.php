<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Src\Admin\BlogAdminService;

class BlogAdminServiceTest extends TestCase
{
    private PDO $pdo;
    private BlogAdminService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Standard test table schema
        $this->pdo->exec("
            CREATE TABLE blogs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                content TEXT NOT NULL,
                ai_summary TEXT NULL,
                summary TEXT NULL,
                blogImg TEXT NULL,
                cover_image TEXT NULL,
                category TEXT NULL,
                author_id TEXT NULL,
                read_time INTEGER NULL,
                status TEXT NULL,
                slug TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )
        ");

        $this->service = new BlogAdminService($this->pdo);
    }

    public function testGetSupportedAppsReturnsAllSixApps(): void
    {
        $apps = $this->service->getSupportedApps();
        $this->assertArrayHasKey('partyplatform', $apps);
        $this->assertArrayHasKey('familyplatform', $apps);
        $this->assertArrayHasKey('execmind', $apps);
        $this->assertArrayHasKey('iaccount', $apps);
        $this->assertArrayHasKey('scoretenant', $apps);
        $this->assertArrayHasKey('loaneasyfinance', $apps);
    }

    public function testCalculateReadTimeEstimatesCorrectMinutes(): void
    {
        // 400 words = 2 minutes
        $words = implode(' ', array_fill(0, 400, 'celebration'));
        $this->assertSame(2, $this->service->calculateReadTime($words));

        // 50 words = minimum 1 minute
        $short = 'Quick tip for host planning.';
        $this->assertSame(1, $this->service->calculateReadTime($short));
    }

    public function testPurifyHtmlStripsDangerousTagsAndProtocols(): void
    {
        $dirty = '<p>Safe intro</p><script>alert("hacked")</script><iframe src="malicious.html"></iframe><a href="javascript:steal()">Click</a>';
        $clean = $this->service->purifyHtml($dirty);

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('<iframe', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringContainsString('<p>Safe intro</p>', $clean);
    }

    public function testPublishArticleInsertsAndReturnsNormalizedPayload(): void
    {
        $data = [
            'title' => 'Top 10 Party Themes For Summer',
            'content' => '<p>Here are the best celebration themes for summer gatherings.</p>',
            'category' => 'Celebration Guide',
            'author_id' => 'Chief Host',
            'cover_image' => 'summer_party.png',
            'status' => 'published',
        ];

        $res = $this->service->publishArticle('partyplatform', $data);

        $this->assertSame('success', $res['status']);
        $this->assertGreaterThan(0, $res['id']);
        $this->assertSame('Top 10 Party Themes For Summer', $res['title']);

        // Verify row in database
        $stmt = $this->pdo->prepare('SELECT * FROM blogs WHERE id = ?');
        $stmt->execute([$res['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotNull($row);
        $this->assertSame('Top 10 Party Themes For Summer', $row['title']);
        $this->assertSame('Celebration Guide', $row['category']);
        $this->assertSame('Chief Host', $row['author_id']);
    }

    public function testGetArticlesReturnsPaginatedItemsAndFilters(): void
    {
        // Seed 3 articles
        $this->service->publishArticle('partyplatform', [
            'title' => 'Alpha Celebration',
            'content' => '<p>Alpha content</p>',
            'category' => 'Guides',
        ]);
        $this->service->publishArticle('partyplatform', [
            'title' => 'Beta Checklist',
            'content' => '<p>Beta content</p>',
            'category' => 'Checklists',
        ]);
        $this->service->publishArticle('partyplatform', [
            'title' => 'Gamma Guide',
            'content' => '<p>Gamma content</p>',
            'category' => 'Guides',
        ]);

        // Query all
        $all = $this->service->getArticles('partyplatform', [], 1, 10);
        $this->assertSame(3, $all['total']);
        $this->assertCount(3, $all['items']);

        // Filter by category
        $filtered = $this->service->getArticles('partyplatform', ['category' => 'Checklists'], 1, 10);
        $this->assertSame(1, $filtered['total']);
        $this->assertSame('Beta Checklist', $filtered['items'][0]['title']);

        // Search by query
        $searched = $this->service->getArticles('partyplatform', ['search' => 'Gamma'], 1, 10);
        $this->assertSame(1, $searched['total']);
        $this->assertSame('Gamma Guide', $searched['items'][0]['title']);
    }

    public function testUpdateArticleUpdatesExistingRecord(): void
    {
        $created = $this->service->publishArticle('partyplatform', [
            'title' => 'Initial Title',
            'content' => '<p>Initial content</p>',
        ]);

        $articleId = $created['id'];

        $updated = $this->service->publishArticle('partyplatform', [
            'title' => 'Revised Title',
            'content' => '<p>Revised content with more tips.</p>',
            'category' => 'Updated Category',
        ], $articleId);

        $this->assertSame('success', $updated['status']);
        $this->assertSame($articleId, $updated['id']);

        $retrieved = $this->service->getArticle('partyplatform', $articleId);
        $this->assertNotNull($retrieved);
        $this->assertSame('Revised Title', $retrieved['title']);
        $this->assertSame('Updated Category', $retrieved['category']);
    }

    public function testDeleteArticleRemovesFromDatabase(): void
    {
        $created = $this->service->publishArticle('partyplatform', [
            'title' => 'To Be Deleted',
            'content' => '<p>Temporary content</p>',
        ]);

        $articleId = $created['id'];
        $this->assertTrue($this->service->deleteArticle('partyplatform', $articleId));

        $retrieved = $this->service->getArticle('partyplatform', $articleId);
        $this->assertNull($retrieved);
    }
}
