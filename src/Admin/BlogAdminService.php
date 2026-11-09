<?php

declare(strict_types=1);

namespace Src\Admin;

use InvalidArgumentException;
use PDO;
use PDOException;
use Src\AuditLogger;
use Src\Db;

/**
 * Sovereign Admin Editorial & Blog Publishing Engine
 * 
 * Provides unified, schema-adaptive editorial operations across portfolio applications:
 *  - PartyPlatform (Social & Celebration Planning)
 *  - FamilyPlatform (Family Hub & Multi-Gen Coordination)
 *  - ExecMind (Executive Career & Advisory Intelligence)
 *  - iAccount (Personal Wealth & Cashflow Intelligence)
 *  - TenantScore (Tenant Credibility & Rental Scoring)
 *  - LoanEasyFinance (Ethical Personal Lending & Affordability)
 */
class BlogAdminService
{
    private PDO $defaultDb;

    /**
     * @var array<string, PDO> Cached database connections per application
     */
    private array $appConnections = [];

    /**
     * @var array<string, array<string, bool>> Cached schema column flags per connection
     */
    private array $schemaCache = [];

    /**
     * Portfolio application registry with their canonical database and editorial metadata
     */
    public const APPS = [
        'partyplatform' => [
            'id' => 'partyplatform',
            'name' => 'PartyPlatform',
            'database' => 'partyplatform',
            'category' => 'Social & Lifestyle',
            'default_category' => 'Celebration Guide',
            'default_author' => 'PartyPlatform Editorial Team',
            'default_image' => 'party_planning.png',
            'url_path' => '/blog',
            'color' => '#ec4899',
            'icon' => 'fa-sparkles',
            'has_slug' => false,
        ],
        'familyplatform' => [
            'id' => 'familyplatform',
            'name' => 'FamilyPlatform',
            'database' => 'family',
            'category' => 'Social & Lifestyle',
            'default_category' => 'Family Dynamics & Parenting',
            'default_author' => 'FamilyPlatform Editorial Team',
            'default_image' => 'family_gathering.jpg',
            'url_path' => '/blog',
            'color' => '#10b981',
            'icon' => 'fa-house-heart',
            'has_slug' => true,
        ],
        'execmind' => [
            'id' => 'execmind',
            'name' => 'ExecMind',
            'database' => 'execmindapp',
            'category' => 'Executive & Advisory',
            'default_category' => 'Executive Intelligence',
            'default_author' => 'ExecMind Editorial Board',
            'default_image' => '1751741373_THINKING_AT_TABLE-min.jpg',
            'url_path' => '/blog',
            'color' => '#6366f1',
            'icon' => 'fa-brain-circuit',
            'has_slug' => false,
        ],
        'iaccount' => [
            'id' => 'iaccount',
            'name' => 'iAccount',
            'database' => 'iaccountapp',
            'category' => 'FinTech & Wealth',
            'default_category' => 'Financial Intelligence',
            'default_author' => 'iAccount Editorial Team',
            'default_image' => '1751741373_THINKING_AT_TABLE-min.jpg',
            'url_path' => '/blog',
            'color' => '#3b82f6',
            'icon' => 'fa-wallet',
            'has_slug' => false,
        ],
        'scoretenant' => [
            'id' => 'scoretenant',
            'name' => 'TenantScore',
            'database' => 'scoretenant',
            'category' => 'PropTech & Trust',
            'default_category' => 'Tenant & Rental Wisdom',
            'default_author' => 'TenantScore Editorial Team',
            'default_image' => '1751741373_THINKING_AT_TABLE-min.jpg',
            'url_path' => '/blog',
            'color' => '#8b5cf6',
            'icon' => 'fa-building-shield',
            'has_slug' => false,
        ],
        'loaneasyfinance' => [
            'id' => 'loaneasyfinance',
            'name' => 'LoanEasyFinance',
            'database' => 'loaneasyfinance',
            'category' => 'FinTech & Lending',
            'default_category' => 'Smart Borrowing & Credit Health',
            'default_author' => 'LoanEasy Finance Editorial Team',
            'default_image' => '1751741373_THINKING_AT_TABLE-min.jpg',
            'url_path' => '/blog',
            'color' => '#059669',
            'icon' => 'fa-hand-holding-dollar',
            'has_slug' => false,
        ],
    ];

    public function __construct(?PDO $db = null)
    {
        $this->defaultDb = $db ?? Db::connect2();
    }

    /**
     * Retrieve supported applications registry.
     * 
     * @return array<string, array<string, mixed>>
     */
    public function getSupportedApps(): array
    {
        return self::APPS;
    }

    /**
     * Connect to the target application's database.
     */
    public function getAppDb(string $targetApp): PDO
    {
        $appKey = strtolower(trim($targetApp));
        if (isset($this->appConnections[$appKey])) {
            return $this->appConnections[$appKey];
        }

        // Check if targetApp matches current default connection database
        $meta = self::APPS[$appKey] ?? null;
        $targetDbName = $meta['database'] ?? $appKey;

        try {
            $currentDb = $this->defaultDb->query('SELECT DATABASE()')->fetchColumn();
            if ($currentDb === $targetDbName) {
                $this->appConnections[$appKey] = $this->defaultDb;
                return $this->defaultDb;
            }
        } catch (\Throwable) {}

        // Connect dynamically using the same credentials as default Db
        $host = (string)($_ENV['DB_HOST'] ?? (getenv('DB_HOST') ?: '127.0.0.1'));
        $user = (string)($_ENV['DB_USERNAME'] ?? (getenv('DB_USERNAME') ?: 'root'));
        $pass = (string)($_ENV['DB_PASSWORD'] ?? (getenv('DB_PASSWORD') ?: ''));
        $charset = 'utf8mb4';

        try {
            $dsn = "mysql:host={$host};dbname={$targetDbName};charset={$charset}";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT => false,
            ]);
            $this->appConnections[$appKey] = $pdo;
            return $pdo;
        } catch (PDOException $e) {
            // Fallback to default connection if dynamic switch fails
            $this->appConnections[$appKey] = $this->defaultDb;
            return $this->defaultDb;
        }
    }

    /**
     * Inspect and cache column capabilities of the target table.
     * 
     * @return array<string, bool>
     */
    public function inspectTableCapabilities(PDO $pdo, string $table = 'blogs'): array
    {
        $cacheKey = spl_object_hash($pdo) . ':' . $table;
        if (isset($this->schemaCache[$cacheKey])) {
            return $this->schemaCache[$cacheKey];
        }

        $capabilities = [
            'slug' => false,
            'summary' => false,
            'ai_summary' => false,
            'cover_image' => false,
            'blogImg' => false,
            'category' => false,
            'author_id' => false,
            'read_time' => false,
            'status' => false,
            'created_at' => false,
            'updated_at' => false,
        ];

        try {
            $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $stmt = $pdo->query("PRAGMA table_info(`{$table}`)");
                if ($stmt) {
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $col) {
                        $field = (string)($col['name'] ?? '');
                        if (array_key_exists($field, $capabilities)) {
                            $capabilities[$field] = true;
                        }
                    }
                }
            } else {
                $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
                if ($stmt) {
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $col) {
                        $field = (string)($col['Field'] ?? '');
                        if (array_key_exists($field, $capabilities)) {
                            $capabilities[$field] = true;
                        }
                    }
                }
            }
        } catch (\Throwable) {}

        $this->schemaCache[$cacheKey] = $capabilities;
        return $capabilities;
    }

    /**
     * Retrieve paginated articles for a specific app with optional filtering.
     * 
     * @param array<string, mixed> $filters
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     per_page: int,
     *     total_pages: int,
     *     app: string,
     *     app_meta: array<string, mixed>
     * }
     */
    public function getArticles(
        string $targetApp = 'partyplatform',
        array $filters = [],
        int $page = 1,
        int $perPage = 15
    ): array {
        $appKey = strtolower(trim($targetApp));
        $appMeta = self::APPS[$appKey] ?? self::APPS['partyplatform'];
        $pdo = $this->getAppDb($appKey);
        $caps = $this->inspectTableCapabilities($pdo, 'blogs');

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];

        // Search query (title or content)
        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(title LIKE ? OR content LIKE ?)';
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Category filter
        $category = trim((string)($filters['category'] ?? ''));
        if ($category !== '' && $category !== 'all' && $caps['category']) {
            $where[] = 'category = ?';
            $params[] = $category;
        }

        // Status filter (if schema supports it)
        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '' && $status !== 'all' && $caps['status']) {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // 1. Total count
        $countSql = "SELECT COUNT(*) AS total FROM blogs {$whereClause}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)($countStmt->fetchColumn() ?: 0);
        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;

        // 2. Query items
        $selectCols = ['id', 'title', 'content'];
        if ($caps['slug']) $selectCols[] = 'slug';
        if ($caps['summary']) $selectCols[] = 'summary';
        if ($caps['ai_summary']) $selectCols[] = 'ai_summary';
        if ($caps['cover_image']) $selectCols[] = 'cover_image';
        if ($caps['blogImg']) $selectCols[] = 'blogImg';
        if ($caps['category']) $selectCols[] = 'category';
        if ($caps['author_id']) $selectCols[] = 'author_id';
        if ($caps['read_time']) $selectCols[] = 'read_time';
        if ($caps['status']) $selectCols[] = 'status';
        if ($caps['created_at']) $selectCols[] = 'created_at';
        if ($caps['updated_at']) $selectCols[] = 'updated_at';

        $colList = implode(', ', $selectCols);
        $orderBy = $caps['created_at'] 
            ? "ORDER BY COALESCE(created_at, updated_at, '1970-01-01 00:00:00') DESC, id DESC"
            : "ORDER BY id DESC";

        $sql = "SELECT {$colList} FROM blogs {$whereClause} {$orderBy} LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($sql);

        $bindIndex = 1;
        foreach ($params as $param) {
            $stmt->bindValue($bindIndex++, $param, PDO::PARAM_STR);
        }
        $stmt->bindValue($bindIndex++, $perPage, PDO::PARAM_INT);
        $stmt->bindValue($bindIndex++, $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $normalizedItems = [];

        foreach ($rows as $row) {
            $normalizedItems[] = $this->normalizeArticleRow($row, $caps, $appMeta);
        }

        return [
            'items' => $normalizedItems,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'app' => $appKey,
            'app_meta' => $appMeta,
        ];
    }

    /**
     * Retrieve single article by ID or slug.
     * 
     * @return array<string, mixed>|null
     */
    public function getArticle(string $targetApp, int|string $identifier): ?array
    {
        $appKey = strtolower(trim($targetApp));
        $appMeta = self::APPS[$appKey] ?? self::APPS['partyplatform'];
        $pdo = $this->getAppDb($appKey);
        $caps = $this->inspectTableCapabilities($pdo, 'blogs');

        $row = null;
        if (is_numeric($identifier)) {
            $stmt = $pdo->prepare('SELECT * FROM blogs WHERE id = ? LIMIT 1');
            $stmt->execute([(int)$identifier]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif ($caps['slug']) {
            $stmt = $pdo->prepare('SELECT * FROM blogs WHERE slug = ? LIMIT 1');
            $stmt->execute([(string)$identifier]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$row) {
            return null;
        }

        return $this->normalizeArticleRow($row, $caps, $appMeta);
    }

    /**
     * Publish or update an article with automated read-time, sanitisation, and slug creation.
     * 
     * @param array{
     *     title: string,
     *     content: string,
     *     category?: string,
     *     summary?: string,
     *     ai_summary?: string,
     *     cover_image?: string,
     *     blogImg?: string,
     *     author_id?: string,
     *     status?: string,
     *     read_time?: int
     * } $data
     * @return array{
     *     status: string,
     *     id: int,
     *     title: string,
     *     slug: ?string,
     *     app: string,
     *     read_time: int,
     *     message: string
     * }
     */
    public function publishArticle(string $targetApp, array $data, ?int $id = null, ?int $adminUserId = null): array
    {
        $appKey = strtolower(trim($targetApp));
        $appMeta = self::APPS[$appKey] ?? self::APPS['partyplatform'];
        $pdo = $this->getAppDb($appKey);
        $caps = $this->inspectTableCapabilities($pdo, 'blogs');

        $title = trim((string)($data['title'] ?? ''));
        $rawContent = (string)($data['content'] ?? '');

        if ($title === '') {
            throw new InvalidArgumentException('Article title is mandatory.');
        }

        if (trim(strip_tags($rawContent)) === '') {
            throw new InvalidArgumentException('Article content cannot be empty.');
        }

        // Sanitise HTML content
        $cleanContent = $this->purifyHtml($rawContent);

        // Calculate reading time
        $readTime = (int)($data['read_time'] ?? $this->calculateReadTime($cleanContent));

        // Generate or clean summary
        $summary = trim((string)($data['summary'] ?? $data['ai_summary'] ?? ''));
        if ($summary === '') {
            $plainText = strip_tags($cleanContent);
            $summary = function_exists('mb_substr') ? mb_substr($plainText, 0, 160) : substr($plainText, 0, 160);
            if (strlen($plainText) > 160) {
                $summary .= '...';
            }
        }

        // Author assignment
        $author = trim((string)($data['author_id'] ?? $appMeta['default_author']));
        if ($author === '') {
            $author = $appMeta['default_author'];
        }

        // Category assignment
        $category = trim((string)($data['category'] ?? $appMeta['default_category']));
        if ($category === '') {
            $category = $appMeta['default_category'];
        }

        // Status assignment
        $status = trim((string)($data['status'] ?? 'published'));
        if (!in_array($status, ['published', 'draft', 'archived'], true)) {
            $status = 'published';
        }

        // Cover image
        $coverImage = trim((string)($data['cover_image'] ?? $data['blogImg'] ?? ''));
        if ($coverImage === '') {
            $coverImage = $appMeta['default_image'] ?? 'party_planning.png';
        }

        // Handle slug if supported
        $slug = null;
        if ($caps['slug']) {
            $slug = $this->generateSlug($title, $pdo, $id);
        }

        $nowExpr = ((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite' ? "datetime('now')" : "NOW()";

        // Execute persistence
        if ($id !== null && $id > 0) {
            // UPDATE EXISTING ARTICLE
            $updateFields = [];
            $params = [];

            $updateFields[] = '`title` = ?';
            $params[] = $title;

            $updateFields[] = '`content` = ?';
            $params[] = $cleanContent;

            if ($caps['slug'] && $slug !== null) {
                $updateFields[] = '`slug` = ?';
                $params[] = $slug;
            }

            if ($caps['summary']) {
                $updateFields[] = '`summary` = ?';
                $params[] = $summary;
            }

            if ($caps['ai_summary']) {
                $updateFields[] = '`ai_summary` = ?';
                $params[] = $summary;
            }

            if ($coverImage !== '') {
                if ($caps['cover_image']) {
                    $updateFields[] = '`cover_image` = ?';
                    $params[] = $coverImage;
                }
                if ($caps['blogImg']) {
                    $updateFields[] = '`blogImg` = ?';
                    $params[] = $coverImage;
                }
            }

            if ($caps['category']) {
                $updateFields[] = '`category` = ?';
                $params[] = $category;
            }

            if ($caps['author_id']) {
                $updateFields[] = '`author_id` = ?';
                $params[] = $author;
            }

            if ($caps['read_time']) {
                $updateFields[] = '`read_time` = ?';
                $params[] = $readTime;
            }

            if ($caps['status']) {
                $updateFields[] = '`status` = ?';
                $params[] = $status;
            }

            if ($caps['updated_at']) {
                $updateFields[] = "`updated_at` = {$nowExpr}";
            }

            $params[] = $id;
            $sql = "UPDATE `blogs` SET " . implode(', ', $updateFields) . " WHERE `id` = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $articleId = $id;
            $action = 'updated';
        } else {
            // INSERT NEW ARTICLE
            $insertCols = ['`title`', '`content`'];
            $placeholders = ['?', '?'];
            $params = [$title, $cleanContent];

            if ($caps['slug'] && $slug !== null) {
                $insertCols[] = '`slug`';
                $placeholders[] = '?';
                $params[] = $slug;
            }

            if ($caps['summary']) {
                $insertCols[] = '`summary`';
                $placeholders[] = '?';
                $params[] = $summary;
            }

            if ($caps['ai_summary']) {
                $insertCols[] = '`ai_summary`';
                $placeholders[] = '?';
                $params[] = $summary;
            }

            if ($caps['cover_image']) {
                $insertCols[] = '`cover_image`';
                $placeholders[] = '?';
                $params[] = $coverImage !== '' ? $coverImage : null;
            }

            if ($caps['blogImg']) {
                $insertCols[] = '`blogImg`';
                $placeholders[] = '?';
                $params[] = $coverImage !== '' ? $coverImage : null;
            }

            if ($caps['category']) {
                $insertCols[] = '`category`';
                $placeholders[] = '?';
                $params[] = $category;
            }

            if ($caps['author_id']) {
                $insertCols[] = '`author_id`';
                $placeholders[] = '?';
                $params[] = $author;
            }

            if ($caps['read_time']) {
                $insertCols[] = '`read_time`';
                $placeholders[] = '?';
                $params[] = $readTime;
            }

            if ($caps['status']) {
                $insertCols[] = '`status`';
                $placeholders[] = '?';
                $params[] = $status;
            }

            if ($caps['created_at']) {
                $insertCols[] = '`created_at`';
                $placeholders[] = $nowExpr;
            }

            if ($caps['updated_at']) {
                $insertCols[] = '`updated_at`';
                $placeholders[] = $nowExpr;
            }

            $sql = sprintf(
                "INSERT INTO `blogs` (%s) VALUES (%s)",
                implode(', ', $insertCols),
                implode(', ', $placeholders)
            );

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $articleId = (int)$pdo->lastInsertId();
            $action = 'published';
        }

        // Audit log action
        if (class_exists(AuditLogger::class)) {
            try {
                AuditLogger::log(
                    'admin.editorial.blog_' . $action,
                    $adminUserId ?? 1,
                    [
                        'target_app' => $appKey,
                        'article_id' => $articleId,
                        'title' => $title,
                        'slug' => $slug,
                        'category' => $category,
                        'status' => $status,
                    ]
                );
            } catch (\Throwable) {}
        }

        return [
            'status' => 'success',
            'id' => $articleId,
            'title' => $title,
            'slug' => $slug,
            'app' => $appKey,
            'read_time' => $readTime,
            'message' => sprintf('Article "%s" %s successfully for %s.', $title, $action, $appMeta['name']),
        ];
    }

    /**
     * Delete an article by ID.
     */
    public function deleteArticle(string $targetApp, int $id, ?int $adminUserId = null): bool
    {
        if ($id <= 0) {
            return false;
        }

        $appKey = strtolower(trim($targetApp));
        $pdo = $this->getAppDb($appKey);

        $stmt = $pdo->prepare("DELETE FROM `blogs` WHERE `id` = ?");
        $success = $stmt->execute([$id]);

        if ($success && class_exists(AuditLogger::class)) {
            try {
                AuditLogger::log(
                    'admin.editorial.blog_deleted',
                    $adminUserId ?? 1,
                    ['target_app' => $appKey, 'article_id' => $id]
                );
            } catch (\Throwable) {}
        }

        return $success;
    }

    /**
     * Retrieve unique categories for an app.
     * 
     * @return array<int, string>
     */
    public function getCategories(string $targetApp = 'partyplatform'): array
    {
        $appKey = strtolower(trim($targetApp));
        $appMeta = self::APPS[$appKey] ?? self::APPS['partyplatform'];
        $pdo = $this->getAppDb($appKey);
        $caps = $this->inspectTableCapabilities($pdo, 'blogs');

        if (!$caps['category']) {
            return [$appMeta['default_category']];
        }

        try {
            $stmt = $pdo->query("SELECT DISTINCT `category` FROM `blogs` WHERE `category` IS NOT NULL AND `category` != '' ORDER BY `category` ASC");
            if ($stmt) {
                $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($categories)) {
                    return array_values(array_filter(array_map('strval', $categories)));
                }
            }
        } catch (\Throwable) {}

        return [$appMeta['default_category']];
    }

    /**
     * Calculate estimated reading time based on 200 words per minute.
     */
    public function calculateReadTime(string $content): int
    {
        $plain = strip_tags($content);
        $wordCount = count(preg_split('/\s+/', trim($plain)) ?: []);
        return max(1, (int)ceil($wordCount / 200));
    }

    /**
     * Generate a unique URL slug for the article.
     */
    public function generateSlug(string $title, PDO $pdo, ?int $excludeId = null): string
    {
        $baseSlug = strtolower(trim((string)preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
        if ($baseSlug === '') {
            $baseSlug = 'article-' . substr(uniqid(), -6);
        }

        $slug = $baseSlug;
        $counter = 1;

        while (true) {
            $sql = "SELECT id FROM `blogs` WHERE `slug` = ?";
            $params = [$slug];

            if ($excludeId !== null && $excludeId > 0) {
                $sql .= " AND `id` != ?";
                $params[] = $excludeId;
            }

            $sql .= " LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if (!$stmt->fetch()) {
                break;
            }

            $slug = $baseSlug . '-' . (++$counter);
        }

        return $slug;
    }

    /**
     * Clean and purify HTML markup, defending against XSS while preserving rich typography.
     */
    public function purifyHtml(string $html): string
    {
        // 1. Remove dangerous script and executable tags
        $clean = preg_replace('/<(script|style|iframe|object|embed|applet|form|input|button|svg|canvas)[^>]*>.*?<\/\\1>/si', '', $html) ?? $html;
        $clean = preg_replace('/<(script|style|iframe|object|embed|applet|form|input|button|svg|canvas)[^>]*>/si', '', $clean) ?? $clean;

        // 2. Strip event handlers and javascript: / data: protocols
        $clean = preg_replace('/\s*on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
        $clean = preg_replace('/(href|src)\s*=\s*["\']\s*(javascript|data|vbscript):[^"\']*["\']/i', '$1="#"', $clean) ?? $clean;

        // 3. Normalize line breaks and spaces
        return trim($clean);
    }

    /**
     * Normalize database row to a predictable output dictionary.
     * 
     * @param array<string, mixed> $row
     * @param array<string, bool> $caps
     * @param array<string, mixed> $appMeta
     * @return array<string, mixed>
     */
    private function normalizeArticleRow(array $row, array $caps, array $appMeta): array
    {
        $id = (int)($row['id'] ?? 0);
        $title = (string)($row['title'] ?? '');
        $content = (string)($row['content'] ?? '');
        $summary = (string)($row['summary'] ?? $row['ai_summary'] ?? '');
        $coverImage = (string)($row['cover_image'] ?? $row['blogImg'] ?? '');
        $category = (string)($row['category'] ?? $appMeta['default_category']);
        $author = (string)($row['author_id'] ?? $appMeta['default_author']);
        $status = (string)($row['status'] ?? 'published');
        $readTime = isset($row['read_time']) ? (int)$row['read_time'] : $this->calculateReadTime($content);
        $slug = (string)($row['slug'] ?? '');
        $createdAt = (string)($row['created_at'] ?? date('Y-m-d H:i:s'));
        $updatedAt = (string)($row['updated_at'] ?? $createdAt);

        // Compute full preview link
        $urlPath = rtrim((string)$appMeta['url_path'], '/');
        $articleUrl = $slug !== '' ? "{$urlPath}/{$slug}" : "{$urlPath}/{$id}";

        return [
            'id' => $id,
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'summary' => $summary,
            'cover_image' => $coverImage,
            'category' => $category,
            'author_id' => $author,
            'status' => $status,
            'read_time' => $readTime,
            'article_url' => $articleUrl,
            'app_id' => $appMeta['id'],
            'app_name' => $appMeta['name'],
            'app_color' => $appMeta['color'],
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ];
    }
}
