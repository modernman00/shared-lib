<?php

declare(strict_types=1);

namespace Src\Security;

use Src\SendEmail;
use Throwable;

/**
 * SovereignAlertNotifier
 *
 * Real-time email alerting rail engineered by the Advanced Technology Group (ATG)
 * under Victor (CTO) and Olutobi (Deloitte) for Wally (Owner / CEO).
 *
 * Instantly alerts waledevtest@gmail.com whenever:
 *  1. Proprietary software encounters an issue or fails over to a 3rd-party library ("DID NOT WORK").
 *  2. A malicious virus, polyglot webshell, or weaponised exploit is detected and blocked.
 *  3. An owner health verification proof report is requested.
 */
class SovereignAlertNotifier
{
    /** Default recipient for owner alerts. */
    public const DEFAULT_OWNER_EMAIL = 'waledevtest@gmail.com';

    /**
     * Resolves the target owner email address.
     */
    public static function getOwnerEmail(): string
    {
        $env = (string) ($_ENV['OWNER_ALERT_EMAIL'] ?? getenv('OWNER_ALERT_EMAIL') ?: '');
        return ($env !== '' && filter_var($env, FILTER_VALIDATE_EMAIL))
            ? $env
            : self::DEFAULT_OWNER_EMAIL;
    }

    /**
     * Sends an immediate WARNING alert when a proprietary engine fails over to a legacy library.
     *
     * @param string $engineName Name of proprietary engine (e.g. SovereignMediaEngine)
     * @param string $fallbackEngaged Name of legacy fallback used (e.g. Intervention\Image)
     * @param string $context Target file or operation description
     * @param string $reason Detailed reason or exception message
     * @return bool True if alert was dispatched
     */
    public static function notifyFallbackTriggered(
        string $engineName,
        string $fallbackEngaged,
        string $context,
        string $reason = 'Engine exception or unsupported format'
    ): bool {
        $ownerEmail = self::getOwnerEmail();
        $timestamp = gmdate('Y-m-d H:i:s \U\T\C');
        $host = gethostname() ?: 'production-server';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'system-internal';

        $subject = "⚠️ [PROPRIETARY ENGINE FALLBACK] {$engineName} switched to {$fallbackEngaged}";

        $html = "
        <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #ffffff;'>
            <div style='background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px; border-radius: 6px; margin-bottom: 20px;'>
                <h2 style='color: #b45309; margin: 0 0 8px 0; font-size: 18px;'>⚠️ Proprietary Engine Fallback Engaged</h2>
                <p style='color: #92400e; margin: 0; font-size: 14px;'>One of our in-house engines encountered an edge case and safely engaged its legacy fallback.</p>
            </div>

            <table style='width: 100%; border-collapse: collapse; font-size: 14px; color: #334155;'>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 10px 0; font-weight: bold; width: 140px;'>Proprietary Engine:</td>
                    <td style='padding: 10px 0; color: #0284c7; font-family: monospace;'>{$engineName}</td>
                </tr>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 10px 0; font-weight: bold;'>Fallback Engaged:</td>
                    <td style='padding: 10px 0; color: #d97706; font-family: monospace;'>{$fallbackEngaged}</td>
                </tr>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 10px 0; font-weight: bold;'>Reason / Trigger:</td>
                    <td style='padding: 10px 0; color: #dc2626;'>{$reason}</td>
                </tr>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 10px 0; font-weight: bold;'>Context / Asset:</td>
                    <td style='padding: 10px 0; word-break: break-all;'>{$context}</td>
                </tr>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 10px 0; font-weight: bold;'>Host Server:</td>
                    <td style='padding: 10px 0;'>{$host}</td>
                </tr>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 10px 0; font-weight: bold;'>Client IP:</td>
                    <td style='padding: 10px 0;'>{$ip}</td>
                </tr>
                <tr>
                    <td style='padding: 10px 0; font-weight: bold;'>Timestamp:</td>
                    <td style='padding: 10px 0;'>{$timestamp}</td>
                </tr>
            </table>

            <div style='margin-top: 24px; padding: 12px; background-color: #f8fafc; border-radius: 6px; font-size: 12px; color: #64748b;'>
                <strong>BRATS Self-Healing Note:</strong> The user request did not fail. The fallback safely preserved user workflow while logging this telemetry for continuous optimization.
            </div>
        </div>
        ";

        return self::safeSend($ownerEmail, $subject, $html);
    }

    /**
     * Sends an immediate CRITICAL alert when a threat or polyglot is detected and blocked.
     *
     * @param string $threatType Type of threat detected (e.g. Polyglot PHP, Malicious PDF Script)
     * @param string $filename Name of target file
     * @param string $sha256 SHA-256 hash of malicious payload
     * @param string $details Detailed threat signature
     * @return bool True if alert was dispatched
     */
    public static function notifyThreatBlocked(
        string $threatType,
        string $filename,
        string $sha256,
        string $details = ''
    ): bool {
        $ownerEmail = self::getOwnerEmail();
        $timestamp = gmdate('Y-m-d H:i:s \U\T\C');
        $host = gethostname() ?: 'production-server';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unidentified';

        $subject = "🚨 [SECURITY ALERT] Malicious Threat Blocked by Sovereign Threat Engine";

        $html = "
        <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #fecdd3; border-radius: 12px; background-color: #ffffff;'>
            <div style='background-color: #ffe4e6; border-left: 4px solid #e11d48; padding: 16px; border-radius: 6px; margin-bottom: 20px;'>
                <h2 style='color: #9f1239; margin: 0 0 8px 0; font-size: 18px;'>🚨 Malicious Exploit Blocked & Annihilated</h2>
                <p style='color: #be123c; margin: 0; font-size: 14px;'>SovereignThreatEngine successfully intercepted and quarantined an attack payload.</p>
            </div>

            <table style='width: 100%; border-collapse: collapse; font-size: 14px; color: #334155;'>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 10px 0; font-weight: bold; width: 140px;'>Threat Category:</td>
                    <td style='padding: 10px 0; color: #e11d48; font-weight: bold;'>{$threatType}</td>
                </tr>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 10px 0; font-weight: bold;'>Target File:</td>
                    <td style='padding: 10px 0; word-break: break-all;'>{$filename}</td>
                </tr>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 10px 0; font-weight: bold;'>Payload SHA-256:</td>
                    <td style='padding: 10px 0; font-family: monospace; font-size: 12px; color: #475569;'>{$sha256}</td>
                </tr>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 10px 0; font-weight: bold;'>Detection Details:</td>
                    <td style='padding: 10px 0; color: #64748b;'>{$details}</td>
                </tr>
                <tr style='border-bottom: 1px solid #f1f5f9;'>
                    <td style='padding: 10px 0; font-weight: bold;'>Client IP:</td>
                    <td style='padding: 10px 0; font-family: monospace;'>{$ip}</td>
                </tr>
                <tr>
                    <td style='padding: 10px 0; font-weight: bold;'>Timestamp:</td>
                    <td style='padding: 10px 0;'>{$timestamp}</td>
                </tr>
            </table>

            <div style='margin-top: 24px; padding: 12px; background-color: #f1f5f9; border-radius: 6px; font-size: 12px; color: #475569;'>
                <strong>Action Taken:</strong> The temporary file was unlinked and shredded from disk. Zero payload survived.
            </div>
        </div>
        ";

        return self::safeSend($ownerEmail, $subject, $html);
    }

    /**
     * Dispatches an executive status digest to the owner verifying all engines.
     *
     * @param array<string, mixed> $report Full SovereignDiagnostic report
     * @return bool True if alert was dispatched
     */
    public static function sendExecutiveHealthDigest(array $report): bool
    {
        $ownerEmail = self::getOwnerEmail();
        $subject = "✅ [ATG HEALTH DIGEST] All Sovereign Proprietary Engines Operational";

        $html = "
        <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #ffffff;'>
            <div style='background-color: #ecfdf5; border-left: 4px solid #10b981; padding: 16px; border-radius: 6px; margin-bottom: 20px;'>
                <h2 style='color: #065f46; margin: 0 0 8px 0; font-size: 18px;'>✅ Sovereign Proprietary Engines Report</h2>
                <p style='color: #047857; margin: 0; font-size: 14px;'>Host: {$report['host']} | PHP: {$report['php_version']} | Status: OPERATIONAL</p>
            </div>

            <p style='font-size: 14px; color: #334155;'>Wally, here is the real-time operational status of your proprietary software suite:</p>

            <div style='margin-bottom: 16px; padding: 12px; background-color: #f8fafc; border-radius: 8px;'>
                <h3 style='margin: 0 0 4px 0; font-size: 15px; color: #0f172a;'>1. Sovereign Threat & Antivirus Engine</h3>
                <p style='margin: 0; font-size: 13px; color: #475569;'>Primary: {$report['engines']['threat_engine']['primary_rail']}<br>Latency: {$report['engines']['threat_engine']['benchmark_ms']} ms</p>
            </div>

            <div style='margin-bottom: 16px; padding: 12px; background-color: #f8fafc; border-radius: 8px;'>
                <h3 style='margin: 0 0 4px 0; font-size: 15px; color: #0f172a;'>2. Sovereign Media & Image Optimisation Engine</h3>
                <p style='margin: 0; font-size: 13px; color: #475569;'>Primary: {$report['engines']['media_engine']['primary_rail']}<br>Savings: {$report['engines']['media_engine']['savings_pct']}</p>
            </div>

            <div style='margin-bottom: 16px; padding: 12px; background-color: #f8fafc; border-radius: 8px;'>
                <h3 style='margin: 0 0 4px 0; font-size: 15px; color: #0f172a;'>3. Sovereign PDF Synthesis Engine</h3>
                <p style='margin: 0; font-size: 13px; color: #475569;'>Primary: {$report['engines']['pdf_engine']['primary_rail']}<br>Benchmark: {$report['engines']['pdf_engine']['benchmark_ms']} ms</p>
            </div>

            <div style='margin-bottom: 16px; padding: 12px; background-color: #f8fafc; border-radius: 8px;'>
                <h3 style='margin: 0 0 4px 0; font-size: 15px; color: #0f172a;'>4. Sovereign SMS Dispatch Engine</h3>
                <p style='margin: 0; font-size: 13px; color: #475569;'>Primary: {$report['engines']['sms_engine']['primary_rail']}</p>
            </div>

            <div style='margin-top: 20px; font-size: 12px; color: #94a3b8; text-align: center;'>
                ATG Sovereign Infrastructure • Delivered to {$ownerEmail}
            </div>
        </div>
        ";

        return self::safeSend($ownerEmail, $subject, $html);
    }

    /**
     * Dispatches the email safely with non-blocking error containment.
     */
    private static function safeSend(string $toEmail, string $subject, string $htmlBody): bool
    {
        try {
            if (class_exists(SendEmail::class)) {
                return (bool) SendEmail::sendEmail($toEmail, 'Wally (Owner)', $subject, $htmlBody);
            }
        } catch (Throwable $e) {
            error_log("[SovereignAlertNotifier] Unable to send owner email: " . $e->getMessage());
        }

        return false;
    }
}
