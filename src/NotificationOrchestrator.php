<?php

declare(strict_types=1);

namespace Src;

use Src\Db;
use Src\PushNotificationService;
use Pusher\Pusher as PusherClient;
use PDO;

/**
 * Universal Multi-Channel Notification Orchestrator
 *
 * Provides a unified dispatch engine for all portfolio platforms:
 * (FamilyPlatform, PartyPlatform, LoanEasyFinance, iAccountApp, ExecMindApp, iDecide)
 *
 * Implements:
 * - Presence-aware arbitration (Pusher In-App vs WebPush vs Email)
 * - Cross-device bi-directional read synchronization
 * - Home Screen Red Badge increment/clear via PWA Badging API
 * - Automatic delayed email fallback suppression
 */
final class NotificationOrchestrator
{
    /**
     * Dispatch a notification across the intelligent cascade.
     *
     * @param string $userId
     * @param string $category 'social'|'financial'|'security'|'system'
     * @param string $priority 'low'|'medium'|'high'|'critical'
     * @param string $title
     * @param string $body
     * @param string $actionUrl
     * @param string $tag Coalescing tag (e.g. 'post-94819')
     * @param string|null $scopeCode Optional family/team/workspace scope
     * @param array<string, mixed>|null $metadata Optional JSON payload
     * @return string Generated Notification ID
     */
    public static function dispatch(
        string $userId,
        string $category,
        string $priority,
        string $title,
        string $body,
        string $actionUrl = '/',
        string $tag = 'general',
        ?string $scopeCode = null,
        ?array $metadata = null
    ): string {
        $notificationId = 'notif_' . bin2hex(random_bytes(12));
        $userId = trim($userId);
        if ($userId === '') {
            return '';
        }

        try {
            $pdo = Db::connect2();

            // 1. Insert into notification_orchestration if table exists
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO notification_orchestration 
                    (id, user_id, family_code, category, priority, title, body, action_url, tag, data_payload, status)
                    VALUES (:id, :uid, :fam, :cat, :pri, :title, :body, :url, :tag, :meta, 'pending')
                ");
                $stmt->execute([
                    ':id'    => $notificationId,
                    ':uid'   => $userId,
                    ':fam'   => $scopeCode,
                    ':cat'   => in_array($category, ['social', 'financial', 'security', 'system'], true) ? $category : 'social',
                    ':pri'   => in_array($priority, ['low', 'medium', 'high', 'critical'], true) ? $priority : 'medium',
                    ':title' => strip_tags($title),
                    ':body'  => strip_tags($body),
                    ':url'   => $actionUrl !== '' ? $actionUrl : '/',
                    ':tag'   => preg_replace('/[^A-Za-z0-9_-]/', '', $tag) ?: 'general',
                    ':meta'  => $metadata !== null ? json_encode($metadata) : null,
                ]);
            } catch (\Throwable $e) {
                // Table might not exist yet; fail-safe logging
                error_log('[NotificationOrchestrator] Orchestration table write skipped: ' . $e->getMessage());
            }

            // 2. Also insert into standard `notification` table if present
            try {
                $legacyStmt = $pdo->prepare("
                    INSERT INTO notification 
                    (sender_id, receiver_id, notification_name, notification_type, notification_content, notification_status, notification_date)
                    VALUES (:sender, :receiver, :name, :type, :content, 'new', :notif_date)
                ");
                $legacyStmt->execute([
                    ':sender'     => $metadata['sender_id'] ?? 'system',
                    ':receiver'   => $userId,
                    ':name'       => strip_tags($title),
                    ':type'       => $category,
                    ':content'    => strip_tags($body),
                    ':notif_date' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable $e) {
                // Ignore if legacy schema differs
            }

            $unreadCount = self::getUnreadCount($userId);

            // 3. Check Presence
            $isUserOnline = self::isUserOnline($userId);

            if ($isUserOnline) {
                // Channel 1: In-App Pusher Socket Broadcast
                $userChannel = 'private-user-' . preg_replace('/[^A-Za-z0-9_-]/', '', $userId);
                self::broadcastPusher($userChannel, 'new-notification', [
                    'id'           => $notificationId,
                    'title'        => $title,
                    'body'         => $body,
                    'action_url'   => $actionUrl,
                    'tag'          => $tag,
                    'unread_count' => $unreadCount,
                    'category'     => $category,
                    'metadata'     => $metadata ?? []
                ]);

                self::logDelivery($notificationId, 'in_app_socket', 'sent', 'Broadcast to active socket');

                // If critical, also sync background badge via silent push
                if ($priority === 'critical') {
                    PushNotificationService::sendPush(
                        userId: $userId,
                        message: $body,
                        url: $actionUrl,
                        title: $title,
                        tag: $tag,
                        badgeCount: $unreadCount,
                        isSilent: true
                    );
                }
            } else {
                // Channel 2: High-Priority OS WebPush with Sound & Badging
                $hasPushSubscriptions = !empty(PushNotificationService::getUserPushSubscriptions($userId));
                $pushed = false;

                if ($hasPushSubscriptions) {
                    $pushed = PushNotificationService::sendPush(
                        userId: $userId,
                        message: $body,
                        url: $actionUrl,
                        title: $title,
                        tag: $tag,
                        badgeCount: $unreadCount,
                        isSilent: false,
                        syncAction: null,
                        targetNotificationId: $notificationId,
                        options: $metadata
                    );
                    self::logDelivery($notificationId, 'web_push', $pushed ? 'sent' : 'failed', 'Dispatched OS WebPush');
                } else {
                    self::logDelivery($notificationId, 'web_push', 'skipped_no_subscription', 'No active push endpoint registered for user');
                }

                // Channel 3: Queue or Immediately Trigger Fallback
                if (in_array($priority, ['high', 'critical'], true)) {
                    if (!$hasPushSubscriptions || !$pushed) {
                        // User is offline with no push capability: trigger instant fallback queue
                        self::logDelivery($notificationId, 'email', 'queued_immediate', 'Queued for immediate delivery (no push device)');
                    } else {
                        self::logDelivery($notificationId, 'email', 'queued', 'Queued for 15-minute fallback');
                    }
                }
            }

            return $notificationId;
        } catch (\Throwable $e) {
            error_log('[NotificationOrchestrator] Dispatch error: ' . $e->getMessage());
            return $notificationId;
        }
    }

    /**
     * Synchronized Cross-Device Mark as Read
     */
    public static function markAsRead(string $notificationId, string $userId): bool
    {
        $notificationId = trim($notificationId);
        $userId = trim($userId);
        if ($notificationId === '' || $userId === '') {
            return false;
        }

        try {
            $pdo = Db::connect2();

            // 1. Update orchestration table
            try {
                $stmt = $pdo->prepare("
                    UPDATE notification_orchestration 
                    SET status = 'read', read_at = NOW() 
                    WHERE id = :id AND user_id = :uid
                ");
                $stmt->execute([':id' => $notificationId, ':uid' => $userId]);
            } catch (\Throwable $e) {}

            // 2. Update legacy table
            try {
                $legStmt = $pdo->prepare("
                    UPDATE notification 
                    SET notification_status = 'deleted' 
                    WHERE receiver_id = :uid AND (no = :notif_no OR notification_name = :notif_id)
                ");
                $legStmt->execute([
                    ':uid'      => $userId,
                    ':notif_no' => is_numeric($notificationId) ? (int)$notificationId : 0,
                    ':notif_id' => $notificationId,
                ]);
            } catch (\Throwable $e) {}

            $unreadCount = self::getUnreadCount($userId);

            // 3. Broadcast sync event to open tabs
            $userChannel = 'private-user-' . preg_replace('/[^A-Za-z0-9_-]/', '', $userId);
            self::broadcastPusher($userChannel, 'notification-synced', [
                'action'          => 'READ',
                'notification_id' => $notificationId,
                'unread_count'    => $unreadCount
            ]);

            // 4. Silent sync push to close OS banners on other devices & reset badge
            PushNotificationService::sendPush(
                userId: $userId,
                message: '',
                url: '',
                title: '',
                tag: 'sync-dismiss',
                badgeCount: $unreadCount,
                isSilent: true,
                syncAction: 'CLOSE_NOTIFICATION',
                targetNotificationId: $notificationId
            );

            // 5. Abort pending email fallback
            try {
                $cancelStmt = $pdo->prepare("
                    UPDATE notification_delivery_logs 
                    SET status = 'cancelled_already_read' 
                    WHERE notification_id = :id AND channel = 'email' AND status = 'queued'
                ");
                $cancelStmt->execute([':id' => $notificationId]);
            } catch (\Throwable $e) {}

            return true;
        } catch (\Throwable $e) {
            error_log('[NotificationOrchestrator] markAsRead failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get unread count
     */
    public static function getUnreadCount(string $userId): int
    {
        try {
            $pdo = Db::connect2();
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM notification_orchestration 
                WHERE user_id = :uid AND status = 'pending'
            ");
            $stmt->execute([':uid' => $userId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Check if user is actively online
     */
    public static function isUserOnline(string $userId): bool
    {
        try {
            $pdo = Db::connect2();
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM user_socket_presence 
                WHERE user_id = :uid 
                  AND is_active = 1 
                  AND last_heartbeat >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
            ");
            $stmt->execute([':uid' => $userId]);
            return ((int)$stmt->fetchColumn()) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Update user socket presence
     */
    public static function updatePresence(string $userId, string $channelName, bool $isActive): void
    {
        try {
            $pdo = Db::connect2();
            $stmt = $pdo->prepare("
                INSERT INTO user_socket_presence (user_id, channel_name, is_active, last_heartbeat)
                VALUES (:uid, :channel, :active, NOW())
                ON DUPLICATE KEY UPDATE is_active = :active_update, last_heartbeat = NOW()
            ");
            $stmt->execute([
                ':uid'           => $userId,
                ':channel'       => $channelName,
                ':active'        => $isActive ? 1 : 0,
                ':active_update' => $isActive ? 1 : 0,
            ]);
        } catch (\Throwable $e) {
            error_log('[NotificationOrchestrator] updatePresence error: ' . $e->getMessage());
        }
    }

    /**
     * Broadcast over Pusher safely
     */
    private static function broadcastPusher(string $channel, string $event, array $data): void
    {
        $key = trim((string)($_ENV['MIX_PUSHER_APP_KEY'] ?? $_ENV['PUSHER_APP_KEY'] ?? (getenv('PUSHER_APP_KEY') ?: '')));
        $secret = trim((string)($_ENV['MIX_PUSHER_APP_SECRET'] ?? $_ENV['PUSHER_APP_SECRET'] ?? (getenv('PUSHER_APP_SECRET') ?: '')));
        $appId = trim((string)($_ENV['MIX_PUSHER_APP_ID'] ?? $_ENV['PUSHER_APP_ID'] ?? (getenv('PUSHER_APP_ID') ?: '')));
        $cluster = trim((string)($_ENV['MIX_PUSHER_APP_CLUSTER'] ?? $_ENV['PUSHER_APP_CLUSTER'] ?? (getenv('PUSHER_APP_CLUSTER') ?: 'eu')));

        if ($key === '' || $secret === '' || $appId === '') {
            return;
        }

        try {
            $pusher = new PusherClient($key, $secret, $appId, [
                'cluster' => $cluster !== '' ? $cluster : 'eu',
                'useTLS'  => true,
                'timeout' => 2,
            ]);
            $pusher->trigger($channel, $event, $data);
        } catch (\Throwable $e) {
            error_log('[NotificationOrchestrator] Pusher broadcast error: ' . $e->getMessage());
        }
    }

    /**
     * Log delivery attempts
     */
    private static function logDelivery(string $notificationId, string $channel, string $status, ?string $details = null): void
    {
        try {
            $pdo = Db::connect2();
            $stmt = $pdo->prepare("
                INSERT INTO notification_delivery_logs (notification_id, channel, status, details, attempted_at)
                VALUES (:nid, :channel, :status, :details, NOW())
            ");
            $stmt->execute([
                ':nid'     => $notificationId,
                ':channel' => $channel,
                ':status'  => $status,
                ':details' => $details,
            ]);
        } catch (\Throwable $e) {}
    }
}
