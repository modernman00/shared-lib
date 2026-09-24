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
            if ($unreadCount <= 0) {
                $unreadCount = 1; // Newly dispatched alert guarantees at least 1 red badge counter
            }

            // 3. Channel 1: In-App Pusher Socket Broadcast (if active)
            $isUserOnline = self::isUserOnline($userId);
            if ($isUserOnline) {
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
            }

            // 4. Channel 2: High-Priority OS WebPush to all registered devices
            $hasPushSubscriptions = !empty(PushNotificationService::getUserPushSubscriptions($userId));
            $pushed = false;

            if ($hasPushSubscriptions) {
                // Support passing rich options from $metadata into PushNotificationService::sendPush()
                // image, vibrate, actions, renotify, requireInteraction, urgency, ttl, dir, lang, data
                /** @var array<string, mixed> $pushOptions */
                $pushOptions = is_array($metadata) ? $metadata : [];
                $pushOptions['category'] = $pushOptions['category'] ?? $category;
                $pushOptions['notificationId'] = $pushOptions['notificationId'] ?? $notificationId;

                if (isset($metadata['image']) && is_string($metadata['image'])) {
                    $pushOptions['image'] = $metadata['image'];
                }
                if (isset($metadata['vibrate']) && is_array($metadata['vibrate'])) {
                    $pushOptions['vibrate'] = $metadata['vibrate'];
                }
                if (isset($metadata['actions']) && is_array($metadata['actions'])) {
                    $pushOptions['actions'] = $metadata['actions'];
                }
                if (isset($metadata['renotify'])) {
                    $pushOptions['renotify'] = (bool) $metadata['renotify'];
                }
                if (isset($metadata['requireInteraction'])) {
                    $pushOptions['requireInteraction'] = (bool) $metadata['requireInteraction'];
                }
                if (isset($metadata['urgency']) && is_string($metadata['urgency'])) {
                    $pushOptions['urgency'] = $metadata['urgency'];
                } elseif (!isset($pushOptions['urgency'])) {
                    $pushOptions['urgency'] = in_array($priority, ['high', 'critical'], true) ? 'high' : 'normal';
                }
                if (isset($metadata['ttl']) && is_numeric($metadata['ttl'])) {
                    $pushOptions['ttl'] = (int) $metadata['ttl'];
                }
                if (isset($metadata['dir']) && is_string($metadata['dir'])) {
                    $pushOptions['dir'] = $metadata['dir'];
                }
                if (isset($metadata['lang']) && is_string($metadata['lang'])) {
                    $pushOptions['lang'] = $metadata['lang'];
                }
                if (isset($metadata['data']) && is_array($metadata['data'])) {
                    $pushOptions['data'] = $metadata['data'];
                } elseif (!isset($pushOptions['data']) && !empty($metadata)) {
                    $pushOptions['data'] = $metadata;
                }

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
                    options: $pushOptions
                );
                self::logDelivery($notificationId, 'web_push', $pushed ? 'sent' : 'failed', 'Dispatched OS WebPush');
            } else {
                self::logDelivery($notificationId, 'web_push', 'skipped_no_subscription', 'No active push endpoint registered for user');
            }

            // 5. Channel 3: Email Fallback Queue (for high/critical priority when user is offline or has no push device)
            if (in_array($priority, ['high', 'critical'], true)) {
                if (!$hasPushSubscriptions || !$pushed) {
                    if (!$isUserOnline) {
                        self::logDelivery($notificationId, 'email', 'queued_immediate', 'Queued for immediate delivery (no push device / offline)');
                    }
                } else if (!$isUserOnline) {
                    self::logDelivery($notificationId, 'email', 'queued', 'Queued for 15-minute fallback');
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
     *
     * @param string $notificationId
     * @param string $userId
     * @return bool True if mark operation succeeded
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

            // 1. Query original tag for this notification to pass as target_tag for client coalescing
            $targetTag = null;
            try {
                $tagStmt = $pdo->prepare("
                    SELECT tag FROM notification_orchestration 
                    WHERE id = :id AND user_id = :uid
                ");
                $tagStmt->execute([':id' => $notificationId, ':uid' => $userId]);
                $tagVal = $tagStmt->fetchColumn();
                if ($tagVal !== false && $tagVal !== null && $tagVal !== '') {
                    $targetTag = (string) $tagVal;
                }
            } catch (\Throwable $e) {
                // Table might not exist or query failed; graceful degradation
            }

            // 2. Update orchestration table
            try {
                $stmt = $pdo->prepare("
                    UPDATE notification_orchestration 
                    SET status = 'read', read_at = NOW() 
                    WHERE id = :id AND user_id = :uid
                ");
                $stmt->execute([':id' => $notificationId, ':uid' => $userId]);
            } catch (\Throwable $e) {}

            // 3. Update legacy table
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

            // 4. Broadcast sync event to open tabs via Pusher WebSocket
            $userChannel = 'private-user-' . preg_replace('/[^A-Za-z0-9_-]/', '', $userId);
            self::broadcastPusher($userChannel, 'notification-synced', [
                'action'          => 'READ',
                'notification_id' => $notificationId,
                'unread_count'    => $unreadCount,
                'target_tag'      => $targetTag,
            ]);

            // CRITICAL DR. SILAS THORNE / SEGUN GOVERNANCE FIX:
            // DO NOT dispatch an empty silent push (isSilent: true, message: '', title: '') to background mobile devices,
            // because on iOS Safari WebKit PWA, push events without calling showNotification() cause Safari to permanently
            // revoke site push permissions, and on Chromium it displays a generic fallback banner!

            // 5. Abort pending email fallback
            try {
                $cancelStmt = $pdo->prepare("
                    UPDATE notification_delivery_logs 
                    SET status = 'cancelled_already_read' 
                    WHERE notification_id = :id AND channel = 'email' AND status LIKE 'queued%'
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
     * Synchronized Cross-Device Batch Mark All as Read
     *
     * @param string $userId
     * @return bool True if batch mark succeeded
     */
    public static function markAllAsRead(string $userId): bool
    {
        $userId = trim($userId);
        if ($userId === '') {
            return false;
        }

        try {
            $pdo = Db::connect2();

            // 1. Atomic batch update on notification_orchestration
            try {
                $stmt = $pdo->prepare("
                    UPDATE notification_orchestration 
                    SET status = 'read', read_at = NOW() 
                    WHERE user_id = :uid AND status = 'pending'
                ");
                $stmt->execute([':uid' => $userId]);
            } catch (\Throwable $e) {
                error_log('[NotificationOrchestrator] markAllAsRead orchestration error: ' . $e->getMessage());
            }

            // 2. Update legacy notification table for the user
            try {
                $legStmt = $pdo->prepare("
                    UPDATE notification 
                    SET notification_status = 'deleted' 
                    WHERE receiver_id = :uid AND notification_status != 'deleted'
                ");
                $legStmt->execute([':uid' => $userId]);
            } catch (\Throwable $e) {}

            // 3. Broadcast Pusher sync event to active open tabs
            $userChannel = 'private-user-' . preg_replace('/[^A-Za-z0-9_-]/', '', $userId);
            self::broadcastPusher($userChannel, 'notification-synced', [
                'action'       => 'MARK_ALL_READ',
                'unread_count' => 0,
            ]);

            // 4. Cancel pending email delivery logs for this user's notifications
            try {
                $cancelStmt = $pdo->prepare("
                    UPDATE notification_delivery_logs 
                    SET status = 'cancelled_already_read' 
                    WHERE channel = 'email' 
                      AND status LIKE 'queued%' 
                      AND notification_id IN (
                          SELECT id FROM notification_orchestration WHERE user_id = :uid
                      )
                ");
                $cancelStmt->execute([':uid' => $userId]);
            } catch (\Throwable $e) {}

            return true;
        } catch (\Throwable $e) {
            error_log('[NotificationOrchestrator] markAllAsRead failed: ' . $e->getMessage());
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
     *
     * @param string $channel
     * @param string $event
     * @param array<string, mixed> $data
     * @return void
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
     * Log delivery attempts with zero-loss fallback.
     *
     * @param string $notificationId
     * @param string $channel
     * @param string $status
     * @param string|null $details
     * @return void
     */
    public static function logDelivery(string $notificationId, string $channel, string $status, ?string $details = null): void
    {
        try {
            $pdo = Db::connect2();
            try {
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
                return;
            } catch (\Throwable) {
                // Fallback if schema has created_at instead of attempted_at
                $stmt = $pdo->prepare("
                    INSERT INTO notification_delivery_logs (notification_id, channel, status, details, created_at)
                    VALUES (:nid, :channel, :status, :details, NOW())
                ");
                $stmt->execute([
                    ':nid'     => $notificationId,
                    ':channel' => $channel,
                    ':status'  => $status,
                    ':details' => $details,
                ]);
                return;
            }
        } catch (\Throwable $e) {
            $fallbackPayload = [
                'notification_id' => $notificationId,
                'channel'         => $channel,
                'status'          => $status,
                'details'         => $details,
                'error'           => $e->getMessage(),
            ];
            error_log('[NOTIFICATION_DELIVERY_LOG_FAILURE] ' . json_encode($fallbackPayload, JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * Retrieve delivery logs for a specific notification.
     *
     * @param string $notificationId
     * @return array<int, array<string, mixed>>
     */
    public static function getDeliveryLogs(string $notificationId): array
    {
        $notificationId = trim($notificationId);
        if ($notificationId === '') {
            return [];
        }

        try {
            $pdo = Db::connect2();

            // Attempt with attempted_at and created_at alias for universal schema compatibility
            try {
                $stmt = $pdo->prepare("
                    SELECT channel, status, details, attempted_at, attempted_at AS created_at 
                    FROM notification_delivery_logs 
                    WHERE notification_id = :nid 
                    ORDER BY id ASC
                ");
                $stmt->execute([':nid' => $notificationId]);
                /** @var array<int, array<string, mixed>> $rows */
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return $rows;
            } catch (\Throwable) {
                // Fallback if schema uses created_at instead of attempted_at
                $stmt = $pdo->prepare("
                    SELECT channel, status, details, created_at, created_at AS attempted_at 
                    FROM notification_delivery_logs 
                    WHERE notification_id = :nid 
                    ORDER BY id ASC
                ");
                $stmt->execute([':nid' => $notificationId]);
                /** @var array<int, array<string, mixed>> $rows */
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return $rows;
            }
        } catch (\Throwable $e) {
            error_log('[NotificationOrchestrator] getDeliveryLogs failed: ' . $e->getMessage());
            return [];
        }
    }
}
