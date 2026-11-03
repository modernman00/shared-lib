<?php

declare(strict_types=1);

namespace Src\Realtime;

use PDO;
use Src\Db;
use RuntimeException;

/**
 * SovereignBroadcaster
 *
 * Elite, zero-dependency, self-hosted real-time event broadcasting rail engineered
 * by the Advanced Technology Group (ATG) under Olutobi (Deloitte) and Victor (CTO).
 *
 * Completely replaces commercial Pusher, Ably, and PubNub subscriptions with high-efficiency
 * Server-Sent Events (SSE), FIFO database queues, and HMAC-SHA256 authenticated channels.
 */
class SovereignBroadcaster
{
    private PDO $pdo;
    private string $appKey;
    private string $appSecret;
    private int $eventTtlSeconds;

    public function __construct(
        ?PDO $pdo = null,
        ?string $appKey = null,
        ?string $appSecret = null,
        int $eventTtlSeconds = 300
    ) {
        $this->pdo = $pdo ?? Db::connect2();
        $envKey = (string)($_ENV['BROADCAST_APP_KEY'] ?? getenv('BROADCAST_APP_KEY') ?: ($_ENV['PUSHER_APP_KEY'] ?? getenv('PUSHER_APP_KEY') ?: ''));
        $envSecret = (string)($_ENV['BROADCAST_APP_SECRET'] ?? getenv('BROADCAST_APP_SECRET') ?: ($_ENV['PUSHER_APP_SECRET'] ?? getenv('PUSHER_APP_SECRET') ?: ($_ENV['JWT_SECRET'] ?? getenv('JWT_SECRET') ?: ($_ENV['APP_KEY'] ?? getenv('APP_KEY') ?: ''))));

        $this->appKey = $appKey !== null && $appKey !== ''
            ? $appKey
            : ($envKey !== '' ? $envKey : 'sovereign_app_key');
        $this->appSecret = $appSecret !== null && $appSecret !== ''
            ? $appSecret
            : ($envSecret !== '' ? hash_hmac('sha256', 'sovereign_broadcaster_hmac_v1', $envSecret) : 'sovereign_app_secret');
        $this->eventTtlSeconds = max(10, min($eventTtlSeconds, 86400));
    }

    /**
     * Validates a channel or event identifier against CRLF / SSE frame injection and length abuse
     */
    public static function sanitizeIdentifier(string $identifier, string $label = 'channel'): string
    {
        $trimmed = trim($identifier);
        if ($trimmed === '' || strlen($trimmed) > 191 || !preg_match('/^[A-Za-z0-9_.:@-]+$/', $trimmed)) {
            throw new \InvalidArgumentException("Invalid {$label} identifier.");
        }
        return $trimmed;
    }

    /**
     * Ensures the sovereign broadcasting schema exists in the database
     */
    public function ensureSchema(): void
    {
        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS sovereign_broadcast_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    channel TEXT NOT NULL,
                    event_name TEXT NOT NULL,
                    payload TEXT NOT NULL,
                    socket_id TEXT NULL,
                    created_at INTEGER NOT NULL
                );
                CREATE INDEX IF NOT EXISTS idx_sbe_channel_created ON sovereign_broadcast_events (channel, created_at, id);

                CREATE TABLE IF NOT EXISTS sovereign_presence_members (
                    channel TEXT NOT NULL,
                    user_id TEXT NOT NULL,
                    user_info TEXT NOT NULL,
                    socket_id TEXT NOT NULL,
                    last_seen INTEGER NOT NULL,
                    PRIMARY KEY (channel, user_id, socket_id)
                );
                CREATE INDEX IF NOT EXISTS idx_spm_channel_seen ON sovereign_presence_members (channel, last_seen);
            ");
        } else {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS sovereign_broadcast_events (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    channel VARCHAR(191) NOT NULL,
                    event_name VARCHAR(191) NOT NULL,
                    payload LONGTEXT NOT NULL,
                    socket_id VARCHAR(191) NULL,
                    created_at BIGINT UNSIGNED NOT NULL,
                    INDEX idx_sbe_channel_created (channel, created_at, id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS sovereign_presence_members (
                    channel VARCHAR(191) NOT NULL,
                    user_id VARCHAR(191) NOT NULL,
                    user_info TEXT NOT NULL,
                    socket_id VARCHAR(191) NOT NULL,
                    last_seen BIGINT UNSIGNED NOT NULL,
                    PRIMARY KEY (channel, user_id, socket_id),
                    INDEX idx_spm_channel_seen (channel, last_seen)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        }
    }

    /**
     * Broadcast an event to one or more channels
     *
     * @param string|array<string> $channels Target channel name(s)
     * @param string $event Event identifier (e.g. 'PartyPulse.ScoreUpdated')
     * @param array<string, mixed> $data Event payload data
     * @param string|null $socketId Optional socket identifier to exclude sender
     * @return int Number of event records dispatched
     */
    public function trigger(string|array $channels, string $event, array $data, ?string $socketId = null): int
    {
        $channelList = is_array($channels) ? $channels : [$channels];
        if (empty($channelList)) {
            return 0;
        }

        $cleanEvent = self::sanitizeIdentifier($event, 'event');
        $cleanSocketId = $socketId !== null && $socketId !== '' ? self::sanitizeIdentifier($socketId, 'socket_id') : null;

        $now = time();
        $payloadJson = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payloadJson === false || strlen($payloadJson) > 65536) {
            throw new RuntimeException('Failed to encode event payload or payload exceeds 64KB safety ceiling.');
        }

        $this->ensureSchema();

        $stmt = $this->pdo->prepare("
            INSERT INTO sovereign_broadcast_events (channel, event_name, payload, socket_id, created_at)
            VALUES (:channel, :event_name, :payload, :socket_id, :created_at)
        ");

        $dispatched = 0;
        foreach (array_slice($channelList, 0, 100) as $channel) {
            $trimmed = trim((string)$channel);
            if ($trimmed === '') {
                continue;
            }
            $cleanChannel = self::sanitizeIdentifier($trimmed, 'channel');

            $stmt->execute([
                ':channel' => $cleanChannel,
                ':event_name' => $cleanEvent,
                ':payload' => $payloadJson,
                ':socket_id' => $cleanSocketId,
                ':created_at' => $now,
            ]);
            $dispatched++;
        }

        // Opportunistic asynchronous cleanup of stale events (1 in 50 invocations)
        if (random_int(1, 50) === 1) {
            $this->pruneStaleEvents();
        }

        return $dispatched;
    }

    /**
     * Retrieve events for a channel after a given ID or timestamp
     *
     * @param string $channel Target channel
     * @param int $afterId Minimum event ID
     * @param int $limit Maximum number of events
     * @return array<int, array<string, mixed>>
     */
    public function getEvents(string $channel, int $afterId = 0, int $limit = 100): array
    {
        $cleanChannel = self::sanitizeIdentifier($channel, 'channel');
        $afterId = max(0, $afterId);
        $limit = max(1, min($limit, 250));

        $this->ensureSchema();

        $stmt = $this->pdo->prepare("
            SELECT id, channel, event_name, payload, socket_id, created_at
            FROM sovereign_broadcast_events
            WHERE channel = :channel AND id > :after_id
            ORDER BY id ASC
            LIMIT :limit
        ");

        $stmt->bindValue(':channel', $cleanChannel, PDO::PARAM_STR);
        $stmt->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = [];

        foreach ($rows as $row) {
            $decoded = json_decode((string)$row['payload'], true);
            $results[] = [
                'id' => (int)$row['id'],
                'channel' => (string)$row['channel'],
                'event' => (string)$row['event_name'],
                'data' => is_array($decoded) ? $decoded : [],
                'socket_id' => $row['socket_id'],
                'created_at' => (int)$row['created_at'],
            ];
        }

        return $results;
    }

    /**
     * Authenticates a private or presence channel for a client socket
     * Compatible with Pusher authorization handshake format
     *
     * @param string $channel Channel name (e.g. 'private-user-12' or 'presence-party-room')
     * @param string $socketId Unique client socket identifier
     * @param array<string, mixed> $customData Optional presence member details
     * @return array<string, string> Auth response array containing 'auth' and optionally 'channel_data'
     */
    public function authenticateChannel(string $channel, string $socketId, array $customData = []): array
    {
        $cleanChannel = self::sanitizeIdentifier($channel, 'channel');
        $cleanSocketId = self::sanitizeIdentifier($socketId, 'socket_id');

        if (str_starts_with($cleanChannel, 'presence-')) {
            $channelData = json_encode($customData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
            $signaturePayload = "{$cleanSocketId}:{$cleanChannel}:{$channelData}";
            $signature = hash_hmac('sha256', $signaturePayload, $this->appSecret);

            return [
                'auth' => "{$this->appKey}:{$signature}",
                'channel_data' => $channelData,
            ];
        }

        $signaturePayload = "{$cleanSocketId}:{$cleanChannel}";
        $signature = hash_hmac('sha256', $signaturePayload, $this->appSecret);

        return [
            'auth' => "{$this->appKey}:{$signature}",
        ];
    }

    /**
     * Verifies channel authorization signature
     */
    public function verifyChannelAuth(string $channel, string $socketId, string $authString, ?string $channelData = null): bool
    {
        try {
            $cleanChannel = self::sanitizeIdentifier($channel, 'channel');
            $cleanSocketId = self::sanitizeIdentifier($socketId, 'socket_id');
        } catch (\Throwable) {
            return false;
        }

        $parts = explode(':', $authString, 2);
        if (count($parts) !== 2) {
            return false;
        }

        [$providedKey, $providedSig] = $parts;
        if (!hash_equals($this->appKey, $providedKey)) {
            return false;
        }

        if (str_starts_with($cleanChannel, 'presence-')) {
            $payload = "{$cleanSocketId}:{$cleanChannel}:" . ($channelData ?? '{}');
        } else {
            $payload = "{$cleanSocketId}:{$cleanChannel}";
        }

        $expectedSig = hash_hmac('sha256', $payload, $this->appSecret);
        return hash_equals($expectedSig, $providedSig);
    }

    /**
     * Registers a presence member in a presence channel
     */
    public function registerPresenceMember(string $channel, string $userId, array $userInfo, string $socketId): void
    {
        $cleanChannel = self::sanitizeIdentifier($channel, 'channel');
        $cleanUserId = self::sanitizeIdentifier($userId, 'user_id');
        $cleanSocketId = self::sanitizeIdentifier($socketId, 'socket_id');

        $this->ensureSchema();
        $now = time();
        $infoJson = json_encode($userInfo, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $sql = "INSERT INTO sovereign_presence_members (channel, user_id, user_info, socket_id, last_seen)
                    VALUES (:channel, :user_id, :user_info, :socket_id, :last_seen)
                    ON CONFLICT(channel, user_id, socket_id) DO UPDATE SET last_seen = :last_seen, user_info = :user_info";
        } else {
            $sql = "INSERT INTO sovereign_presence_members (channel, user_id, user_info, socket_id, last_seen)
                    VALUES (:channel, :user_id, :user_info, :socket_id, :last_seen)
                    ON DUPLICATE KEY UPDATE last_seen = VALUES(last_seen), user_info = VALUES(user_info)";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':channel' => $cleanChannel,
            ':user_id' => $cleanUserId,
            ':user_info' => $infoJson,
            ':socket_id' => $cleanSocketId,
            ':last_seen' => $now,
        ]);
    }

    /**
     * Retrieves active presence members for a channel
     *
     * @return array<string, array<string, mixed>>
     */
    public function getPresenceMembers(string $channel, int $activeWindowSeconds = 60): array
    {
        $this->ensureSchema();
        $cutoff = time() - $activeWindowSeconds;

        $stmt = $this->pdo->prepare("
            SELECT user_id, user_info, MAX(last_seen) as last_seen
            FROM sovereign_presence_members
            WHERE channel = :channel AND last_seen >= :cutoff
            GROUP BY user_id, user_info
        ");
        $stmt->execute([
            ':channel' => $channel,
            ':cutoff' => $cutoff,
        ]);

        $members = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $info = json_decode((string)$row['user_info'], true);
            $members[(string)$row['user_id']] = is_array($info) ? $info : [];
        }

        return $members;
    }

    /**
     * Prune stale events older than the TTL
     */
    public function pruneStaleEvents(): int
    {
        $cutoff = time() - $this->eventTtlSeconds;
        $stmt = $this->pdo->prepare("DELETE FROM sovereign_broadcast_events WHERE created_at < :cutoff");
        $stmt->execute([':cutoff' => $cutoff]);
        return $stmt->rowCount();
    }

    /**
     * Executes the Server-Sent Events (SSE) streaming loop
     * Designed to be invoked directly from an SSE HTTP controller endpoint.
     *
     * @param string $channel Target channel to listen for
     * @param int $lastEventId Last event ID acknowledged by the client
     * @param int $maxExecutionSeconds Execution timeout for PHP worker safety
     * @param callable|null $isConnectionAborted Custom abort checker (defaults to connection_aborted())
     */
    public function streamSse(
        string $channel,
        int $lastEventId = 0,
        int $maxExecutionSeconds = 30,
        ?callable $isConnectionAborted = null
    ): void {
        $cleanChannel = self::sanitizeIdentifier($channel, 'channel');
        $lastEventId = max(0, $lastEventId);
        $maxExecutionSeconds = max(1, min($maxExecutionSeconds, 60));

        // Send SSE headers
        if (!headers_sent()) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache, no-transform');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Nginx reverse proxy buffering bypass
        }

        $abortCheck = $isConnectionAborted ?? static fn(): bool => connection_aborted() !== 0;
        $startTime = time();
        $currentId = $lastEventId;

        // Emit initial connection handshake (CRLF-safe via sanitizeIdentifier)
        echo ": connected to sovereign channel {$cleanChannel}\n\n";
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        flush();

        while ((time() - $startTime) < $maxExecutionSeconds) {
            if ($abortCheck()) {
                break;
            }

            $events = $this->getEvents($cleanChannel, $currentId, 25);
            if (!empty($events)) {
                foreach ($events as $event) {
                    $currentId = (int)$event['id'];
                    $safeEventName = str_replace(["\r", "\n"], '', (string)$event['event']);
                    $payloadJson = json_encode([
                        'event' => $safeEventName,
                        'data' => $event['data'],
                        'channel' => $cleanChannel,
                        'created_at' => $event['created_at'],
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                    echo "id: {$currentId}\n";
                    echo "event: {$safeEventName}\n";
                    echo "data: {$payloadJson}\n\n";
                }

                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                flush();
            } else {
                // Send keepalive comment to keep connection active through NAT gateways
                echo ": keepalive\n\n";
                if (function_exists('ob_flush')) {
                    @ob_flush();
                }
                flush();
            }

            // Sleep 500ms before next poll tick
            usleep(500000);
        }
    }
}
