<?php

declare(strict_types=1);

namespace Src;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Src\Select;
use Src\Delete;

/**
 * Centralized WebPush & PWA Notification Service
 *
 * Implements:
 * - Red Team SSRF endpoint allowlisting (Google FCM, Apple APNs, Mozilla, Windows WNS)
 * - VAPID RFC 8292 / RFC 8291 payload encryption
 * - Web Badging API and silent sync action flags
 * - Bounded 3-second cURL timeouts (Gate 4)
 * - Self-healing expired subscription pruning (410 Gone / 404 Not Found)
 */
class PushNotificationService
{
    /**
     * Red Team Allowlist for Push Service Endpoints (SSRF Mitigation)
     */
    public static function isAllowedPushEndpoint(string $endpoint): bool
    {
        $parsed = parse_url($endpoint);
        if (!$parsed || empty($parsed['host']) || empty($parsed['scheme']) || $parsed['scheme'] !== 'https') {
            return false;
        }

        $host = strtolower($parsed['host']);
        $allowedSuffixes = [
            'push.apple.com',
            'fcm.googleapis.com',
            'googleapis.com',
            'push.services.mozilla.com',
            'notify.windows.com',
            'push.amazon.com',
        ];

        foreach ($allowedSuffixes as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send push notification to a user or list of users
     *
     * @param string|int|array<int, string|int>|null $userId
     * @param string $message
     * @param string|null $url
     * @param string $title
     * @param string $tag
     * @param int|null $badgeCount
     * @param bool $isSilent
     * @param string|null $syncAction
     * @param string|null $targetNotificationId
     * @param array<string, mixed>|null $options
     * @return bool
     */
    public static function sendPush(
        string|int|array|null $userId,
        string $message,
        ?string $url = null,
        string $title = 'Platform Alert',
        string $tag = 'general',
        ?int $badgeCount = null,
        bool $isSilent = false,
        ?string $syncAction = null,
        ?string $targetNotificationId = null,
        ?array $options = null
    ): bool {
        if (empty($userId)) {
            return false;
        }

        $userIds = is_array($userId) ? $userId : [$userId];
        $publicKey = (string) (getenv('VAPID_PUBLIC_KEY') ?: ($_ENV['VAPID_PUBLIC_KEY'] ?? ''));
        $privateKey = (string) (getenv('VAPID_PRIVATE_KEY') ?: ($_ENV['VAPID_PRIVATE_KEY'] ?? ''));
        $subject = (string) (getenv('VAPID_SUBJECT') ?: ($_ENV['VAPID_SUBJECT'] ?? 'mailto:support@modernman.org'));
        $appLogo = (string) (getenv('APP_LOGO') ?: ($_ENV['APP_LOGO'] ?? '/public/img/favicon/android-chrome-192x192.png'));

        if (empty($publicKey) || empty($privateKey)) {
            error_log('[PushNotificationService] VAPID keys not configured in environment.');
            return false;
        }

        $auth = [
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ];

        try {
            $defaultOptions = [
                'timeout' => 3, // Bounded 3-second timeout (Gate 4)
            ];
            $webPush = new WebPush($auth, $defaultOptions);
            $webPush->setReuseVAPIDHeaders(true);

            $payloadArray = [
                'title'                => $title,
                'body'                 => $message,
                'url'                  => $url ?: '/',
                'icon'                 => $appLogo,
                'badge'                => '/public/img/favicon/favicon-32x32.png',
                'tag'                  => $tag,
                'badgeCount'           => $badgeCount,
                'isSilent'             => $isSilent,
                'syncAction'           => $syncAction,
                'targetNotificationId' => $targetNotificationId,
                'actions'              => $options['actions'] ?? [],
                'timestamp'            => time() * 1000,
            ];

            $payload = json_encode($payloadArray, JSON_UNESCAPED_SLASHES);
            $hasSubscriptions = false;

            foreach ($userIds as $uid) {
                $uidStr = (string) $uid;
                $subscriptions = self::getUserPushSubscriptions($uidStr);

                foreach ($subscriptions as $sub) {
                    $endpoint = (string)($sub['endpoint'] ?? '');
                    $p256dh = (string)($sub['p256dhKey'] ?? $sub['p256dh'] ?? '');
                    $auth = (string)($sub['authKey'] ?? $sub['auth'] ?? '');

                    if ($endpoint === '' || $p256dh === '' || $auth === '') {
                        continue;
                    }

                    // Red Team SSRF Gatekeeper
                    if (!self::isAllowedPushEndpoint($endpoint)) {
                        error_log("[PushNotificationService] SSRF Blocked: Invalid push endpoint {$endpoint}");
                        continue;
                    }

                    $subscriptionObject = Subscription::create([
                        'endpoint' => $endpoint,
                        'keys' => [
                            'p256dh' => $p256dh,
                            'auth' => $auth,
                        ],
                    ]);

                    $webPush->queueNotification($subscriptionObject, $payload ?: null);
                    $hasSubscriptions = true;
                }
            }

            if (!$hasSubscriptions) {
                return false;
            }

            // Flush all queued notifications & prune expired endpoints (Gate 2)
            foreach ($webPush->flush() as $report) {
                $endpoint = $report->getRequest()->getUri()->__toString();
                if (!$report->isSuccess()) {
                    $reason = $report->getReason();
                    error_log("[PushNotificationService] Failed for {$endpoint}: {$reason}");

                    // If subscription has expired (410 Gone / 404 Not Found), delete from DB
                    if ($report->isSubscriptionExpired()) {
                        try {
                            $db = \Src\Db::connect2();
                            // Prune from pushNotification
                            try {
                                $stmt1 = $db->prepare("DELETE FROM pushNotification WHERE endpoint = ?");
                                $stmt1->execute([$endpoint]);
                            } catch (\Throwable $e) {}

                            // Prune from user_push_subscriptions
                            try {
                                $stmt2 = $db->prepare("DELETE FROM user_push_subscriptions WHERE endpoint = ?");
                                $stmt2->execute([$endpoint]);
                            } catch (\Throwable $e) {}
                        } catch (\Throwable $e) {
                            error_log("[PushNotificationService] Prune error: " . $e->getMessage());
                        }
                    }
                }
            }

            return true;
        } catch (\Throwable $e) {
            error_log('[PushNotificationService] Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch user's active push subscriptions from database across both table schemas
     *
     * @param string $userId
     * @return array<int, array<string, mixed>>
     */
    public static function getUserPushSubscriptions(string $userId): array
    {
        $allSubscriptions = [];

        try {
            $db = \Src\Db::connect2();

            // 1. Try modern user_push_subscriptions table
            try {
                $stmt = $db->prepare('SELECT endpoint, p256dh, auth_token FROM user_push_subscriptions WHERE user_id = ?');
                $stmt->execute([$userId]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    foreach ($rows as $row) {
                        $allSubscriptions[] = [
                            'endpoint' => (string)($row['endpoint'] ?? ''),
                            'p256dhKey' => (string)($row['p256dh'] ?? ''),
                            'authKey'   => (string)($row['auth_token'] ?? $row['auth'] ?? ''),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // Table might not exist; fallback
            }

            // 2. Try legacy pushNotification table
            try {
                $stmt2 = $db->prepare('SELECT endpoint, p256dhKey, authKey FROM pushNotification WHERE id = ?');
                $stmt2->execute([$userId]);
                $rows2 = $stmt2->fetchAll(\PDO::FETCH_ASSOC);
                if (!empty($rows2)) {
                    foreach ($rows2 as $row2) {
                        $allSubscriptions[] = [
                            'endpoint' => (string)($row2['endpoint'] ?? ''),
                            'p256dhKey' => (string)($row2['p256dhKey'] ?? $row2['p256dh'] ?? ''),
                            'authKey'   => (string)($row2['authKey'] ?? $row2['auth'] ?? ''),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // Table might not exist
            }

            return $allSubscriptions;
        } catch (\Throwable $e) {
            error_log('[PushNotificationService] DB Fetch error: ' . $e->getMessage());
            return [];
        }
    }
}
