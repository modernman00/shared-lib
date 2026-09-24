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
     *
     * Strict verification:
     * - Scheme MUST be 'https'
     * - Port MUST be empty/omitted or strictly 443
     * - User and password credentials MUST be empty
     * - Exact hosts: 'fcm.googleapis.com', 'android.googleapis.com'
     * - Exact domain suffixes: '.push.apple.com', '.push.services.mozilla.com', '.notify.windows.com', '.push.amazon.com'
     * - Disallows bare 'googleapis.com' and arbitrary non-push subdomains
     * - Disallows IP addresses (IPv4, IPv6, localhost, 169.254.169.254)
     *
     * @param string $endpoint
     * @return bool
     */
    public static function isAllowedPushEndpoint(string $endpoint): bool
    {
        $parsed = parse_url($endpoint);
        if (!$parsed || empty($parsed['host']) || empty($parsed['scheme'])) {
            return false;
        }

        // Scheme MUST be strictly https
        if (strtolower($parsed['scheme']) !== 'https') {
            return false;
        }

        // Port MUST be empty/omitted or strictly 443
        if (isset($parsed['port']) && $parsed['port'] !== 443) {
            return false;
        }

        // User and pass MUST be empty
        if (!empty($parsed['user']) || !empty($parsed['pass'])) {
            return false;
        }

        $host = trim(strtolower($parsed['host']), '[]');

        // Disallow IP addresses (IPv4, IPv6, localhost, cloud metadata 169.254.169.254)
        if (filter_var($host, FILTER_VALIDATE_IP) !== false || $host === 'localhost') {
            return false;
        }

        // Exact allowed hosts (FCM / GCM)
        $exactHosts = [
            'fcm.googleapis.com',
            'android.googleapis.com',
            'push.apple.com',
            'push.services.mozilla.com',
            'notify.windows.com',
            'push.amazon.com',
        ];

        if (in_array($host, $exactHosts, true)) {
            return true;
        }

        // Exact domain suffixes (Apple APNs, Mozilla Autopush, Windows WNS, Amazon ADM)
        $allowedSuffixes = [
            '.push.apple.com',
            '.push.services.mozilla.com',
            '.notify.windows.com',
            '.push.amazon.com',
        ];

        foreach ($allowedSuffixes as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Send rich PWA Web Push notification to user(s) with native Chromium and WebKit parity.
     *
     * @param string|int|array<int, string|int>|null $userId Target user ID or array of user IDs
     * @param string $message Main notification body text
     * @param string|null $url Navigation destination URL upon clicking notification
     * @param string $title Bold headline title
     * @param string $tag Coalescing identifier (max 32 chars URL-safe)
     * @param int|null $badgeCount Unread counter for Web Badging API (0 clears badge)
     * @param bool $isSilent Backward-compatibility flag for silent notifications
     * @param string|null $syncAction Backward-compatibility action flag (e.g. 'CLOSE_NOTIFICATION')
     * @param string|null $targetNotificationId Backward-compatibility target notification ID
     * @param array<string, mixed>|null $options Rich notification configuration
     * @return bool True if queued/dispatched to at least one valid subscription
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
                'subject'    => $subject,
                'publicKey'  => $publicKey,
                'privateKey' => $privateKey,
            ],
        ];

        try {
            // Gate 4 Bounded cURL Timeouts & Open-Redirect SSRF Mitigation
            $defaultOptions = [];
            $timeout = 3;
            $clientOptions = [
                'timeout'         => 3.0,
                'connect_timeout' => 2.0,
                'allow_redirects' => false,
                'http_errors'     => false,
            ];
            $webPush = new WebPush($auth, $defaultOptions, $timeout, $clientOptions);
            $webPush->setReuseVAPIDHeaders(true);

            // Extract & normalize rich options
            $effectiveUrl = $url ?: (string)($options['url'] ?? '/');
            $rawTag = (string)($options['tag'] ?? $tag);
            $cleanedTag = preg_replace('/[^A-Za-z0-9_-]/', '', $rawTag) ?? '';
            $sanitizedTag = substr($cleanedTag, 0, 32);

            // Renotify & tag normalization (WHATWG throw rule defense: renotify requires non-empty tag)
            $renotify = (bool)($options['renotify'] ?? false);
            if ($sanitizedTag === '') {
                $sanitizedTag = 'general';
            }

            // Silent & vibration conflict resolution (WHATWG throw rule defense)
            $silent = (bool)($options['silent'] ?? $isSilent);
            $isSilentEffective = $silent;

            $vibrate = null;
            if (!$silent && !empty($options['vibrate']) && is_array($options['vibrate'])) {
                $vibrate = array_values(array_map('intval', $options['vibrate']));
            }

            // Media & icons
            $image = isset($options['image']) && is_string($options['image']) && $options['image'] !== ''
                ? $options['image']
                : null;
            if ($image !== null) {
                $appUrl = rtrim((string)(getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? '')), '/');
                if ($appUrl !== '' && str_starts_with($image, '/')) {
                    $image = $appUrl . $image;
                }
            }

            $icon = isset($options['icon']) && is_string($options['icon']) && $options['icon'] !== ''
                ? $options['icon']
                : $appLogo;

            $badge = isset($options['badge']) && is_string($options['badge']) && $options['badge'] !== ''
                ? $options['badge']
                : '/public/img/favicon/favicon-32x32.png';

            // Interaction and presentation flags
            $requireInteraction = (bool)($options['requireInteraction'] ?? false);
            $dir = in_array($options['dir'] ?? '', ['auto', 'ltr', 'rtl'], true) ? (string)$options['dir'] : 'auto';
            $lang = isset($options['lang']) && is_string($options['lang']) && $options['lang'] !== '' ? (string)$options['lang'] : 'en-US';
            $timestamp = isset($options['timestamp']) && is_numeric($options['timestamp'])
                ? (int)$options['timestamp']
                : (int)(round(microtime(true) * 1000));

            // Actions array normalization (max 2 actions, typed action items)
            $actions = [];
            $rawActions = $options['actions'] ?? [];
            if (is_array($rawActions)) {
                $count = 0;
                foreach ($rawActions as $act) {
                    if ($count >= 2) {
                        break;
                    }
                    if (!is_array($act) || empty($act['action']) || empty($act['title'])) {
                        continue;
                    }
                    $actionItem = [
                        'action' => (string)$act['action'],
                        'title'  => (string)$act['title'],
                    ];
                    if (!empty($act['icon']) && is_string($act['icon'])) {
                        $actionItem['icon'] = $act['icon'];
                    }
                    $actType = $act['type'] ?? 'button';
                    if (in_array($actType, ['button', 'text'], true)) {
                        $actionItem['type'] = $actType;
                    }
                    if (!empty($act['placeholder']) && is_string($act['placeholder'])) {
                        $actionItem['placeholder'] = $act['placeholder'];
                    }
                    $actions[] = $actionItem;
                    $count++;
                }
            }

            // RFC 8030 Gateway Header Options
            if (isset($options['ttl']) && is_numeric($options['ttl']) && (int)$options['ttl'] >= 0) {
                $ttl = (int)$options['ttl'];
            } else {
                $ttl = $silent ? 300 : 86400;
            }

            if (!empty($options['urgency']) && in_array($options['urgency'], ['very-low', 'low', 'normal', 'high'], true)) {
                $urgency = (string)$options['urgency'];
            } else {
                $urgency = $silent ? 'low' : 'high';
            }

            $webPushOptions = [
                'TTL'     => $ttl,
                'urgency' => $urgency,
                'topic'   => $sanitizedTag,
            ];

            // Metadata resolution
            $customData = is_array($options['data'] ?? null) ? $options['data'] : [];
            $targetNotifId = $targetNotificationId ?: ($options['targetNotificationId'] ?? ($customData['targetNotificationId'] ?? null));
            $notifId = $targetNotifId ?: ($options['notificationId'] ?? ($customData['notificationId'] ?? null));
            $category = (string)($options['category'] ?? ($customData['category'] ?? 'general'));
            $syncActionEffective = $syncAction ?: ($options['syncAction'] ?? null);

            $hasSubscriptions = false;

            foreach ($userIds as $uid) {
                $uidStr = (string) $uid;
                $subscriptions = self::getUserPushSubscriptions($uidStr);

                // Centralized Automatic Red Counter Resolution:
                // Ensure every active push notification delivers an integer badge counter
                $effectiveBadgeCount = $badgeCount ?? ($options['badgeCount'] ?? null);
                if ($effectiveBadgeCount === null && !$silent) {
                    if (class_exists('\\Src\\NotificationOrchestrator')) {
                        try {
                            $computed = \Src\NotificationOrchestrator::getUnreadCount($uidStr);
                            $effectiveBadgeCount = $computed > 0 ? $computed : 1;
                        } catch (\Throwable) {
                            $effectiveBadgeCount = 1;
                        }
                    } else {
                        $effectiveBadgeCount = 1;
                    }
                }

                $clearBadge = ($effectiveBadgeCount === 0) || !empty($options['clearBadge']);

                // Nested data object containing url, tag, badgeCount, notificationId, actions, and custom data
                $nestedData = array_merge($customData, [
                    'url'                  => $effectiveUrl,
                    'tag'                  => $sanitizedTag,
                    'badgeCount'           => $effectiveBadgeCount,
                    'clearBadge'           => $clearBadge,
                    'notificationId'       => $notifId,
                    'targetNotificationId' => $targetNotifId,
                    'category'             => $category,
                    'actions'              => $actions,
                ]);

                // Dual-Level Payload Structure (21 top-level keys matching W3C + backward-compat)
                $userPayloadArray = [
                    'title'                => $title,
                    'body'                 => $message,
                    'url'                  => $effectiveUrl,
                    'icon'                 => $icon,
                    'badge'                => $badge,
                    'image'                => $image,
                    'tag'                  => $sanitizedTag,
                    'badgeCount'           => $effectiveBadgeCount,
                    'clearBadge'           => $clearBadge,
                    'vibrate'              => $vibrate,
                    'renotify'             => $renotify,
                    'silent'               => $silent,
                    'requireInteraction'   => $requireInteraction,
                    'dir'                  => $dir,
                    'lang'                 => $lang,
                    'actions'              => $actions,
                    'data'                 => $nestedData,
                    'timestamp'            => $timestamp,
                    'isSilent'             => $isSilentEffective,
                    'syncAction'           => $syncActionEffective,
                    'targetNotificationId' => $targetNotifId,
                ];

                $payload = json_encode($userPayloadArray, JSON_UNESCAPED_SLASHES);

                foreach ($subscriptions as $sub) {
                    $endpoint = (string)($sub['endpoint'] ?? '');
                    $p256dh = (string)($sub['p256dhKey'] ?? $sub['p256dh'] ?? '');
                    $authKey = (string)($sub['authKey'] ?? $sub['auth'] ?? '');

                    if ($endpoint === '' || $p256dh === '' || $authKey === '') {
                        continue;
                    }

                    // Red Team SSRF Gatekeeper
                    if (!self::isAllowedPushEndpoint($endpoint)) {
                        error_log("[PushNotificationService] SSRF Blocked: Invalid push endpoint {$endpoint}");
                        continue;
                    }

                    $subscriptionObject = Subscription::create([
                        'endpoint'        => $endpoint,
                        'keys'            => [
                            'p256dh' => $p256dh,
                            'auth'   => $authKey,
                        ],
                        'contentEncoding' => 'aes128gcm',
                    ]);

                    $webPush->queueNotification($subscriptionObject, $payload ?: null, $webPushOptions);
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
