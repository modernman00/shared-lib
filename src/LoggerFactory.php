<?php

declare(strict_types=1);

namespace Src;

use Monolog\Formatter\HtmlFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\SymfonyMailerHandler;
use Monolog\Level;
use Monolog\Logger;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

/**
 * 📦 LoggerFactory builds a logger that:
 *   • writes logs to a file
 *   • emails you if there's an error
 */
final class LoggerFactory
{
    private static ?Logger $logger = null;

    /**
     * Creates a configured logger with file + email handlers.
     *
     * @param string $name The log channel name (e.g., 'app', 'errors')
     * @param string $logPath The path to save log files
     * @param string $mailerDsn SMTP DSN for sending emails
     * @param string $fromEmail Sender address for alerts
     * @param string $toEmail Recipient address for alerts
     * @param Level $level The minimum level to log (Email triggers at error or higher)
     *
     * @example $logger = LoggerFactory::createWithMailer('app', 'qLz1s@example.com',  Level::Error);
     * $logger->info('This is an info message');
     * $logger->error('This is an error message'); --- to trigger email alert
     * $logger->info: Informational messages (e.g., $logger->info('User logged in');).
     * remember to use Monolog\Level for the $level parameter i.e use Monolog\Level Level::Error Level::Info etc.;
     * warning: Potential issues (e.g., $logger->warning('Deprecated function used');).
     * error: Error messages (e.g., $logger->error('Database connection failed');).
     * critical: Critical errors (e.g., $logger->critical('System failure');).
     * alert: Alerts that require immediate attention (e.g., $logger->alert('Security breach detected');).
     * emergency: Emergency situations (e.g., $logger->emergency('Server is down');).
     * DONT FORGET TO SET THE ENVIRONMENT VARIABLES:
     *   - LOGGER_NAME: The name of the logger channel (e.g., 'app').
     *   - LOGGER_PATH: The path where log files will be stored (e.g., '/var/log/app.log').
     *   - MAILER_DSN: The DSN for the mailer (e.g., 'smtp://user:password@localhost:25?encryption=tls&auth_mode=login').
     *  - USER_EMAIL: The email address to send alerts from (e.g., 'qLz1s@example.com').
     *
     * @throws \Exception If logger creation fails
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface If email transport fails
     * @throws \Symfony\Component\Mailer\Exception\LogicException If email configuration is incorrect
     * @throws \Symfony\Component\Mailer\Exception\InvalidArgumentException If email arguments are invalid
     * @throws \Symfony\Component\Mailer\Exception\RuntimeException If email sending fails
     *
     * @return Logger Configured logger instance
     */
    public static function createWithMailer(Level $level = Level::Error): Logger
    {
        if (self::$logger !== null) {
            return self::$logger; // Reuse existing logger
        }

        // Validate environment variables
        $requiredEnvVars = ['LOGGER_NAME', 'LOGGER_PATH', 'MAILER_DSN', 'USER_EMAIL'];
        foreach ($requiredEnvVars as $var) {
            if (!isset($_ENV[$var]) || empty(trim($_ENV[$var]))) {
                throw new \InvalidArgumentException("Missing or empty environment variable: $var");
            }
        }
        $logger = new Logger($_ENV['LOGGER_NAME']);

        // Normalize log path. Strip any leading "../" or "./" from the configured
        // path first (its exact dot-count depends on how deep LOGGER_PATH's author
        // guessed the vendor install would be, which isn't reliable), then anchor
        // it to the consuming app's real root: BASE_PATH when the app bootstrap has
        // defined it, otherwise computed from __DIR__ (vendor/{vendor}/{package}/src,
        // so 4 levels up is always the app root for a Composer-installed package).
        $logPath = $_ENV['LOGGER_PATH'];
        if (!str_starts_with($logPath, '/')) {
            $cleanPath = preg_replace('/^(\.\.\/|\.\/)+/', '', $logPath);
            $appRoot = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
            $logPath = $appRoot . '/' . $cleanPath;
        }

        // Write logs to file with rotation ( 7 days)
        $fileHandler = new RotatingFileHandler($logPath, 7, Level::Debug);
        $logger->pushHandler($fileHandler);

        // ✉️ Send email alerts for error or more severe if mail alerts are enabled
        $alertsEnabled = filter_var($_ENV['LOGGER_EMAIL_ALERTS'] ?? true, FILTER_VALIDATE_BOOLEAN);
        if ($alertsEnabled && !empty($_ENV['MAILER_DSN'] ?? '')) {
            $transport = Transport::fromDsn($_ENV['MAILER_DSN']);
            $mailer = new Mailer($transport);

            $email = (new Email())
              ->from($_ENV['USER_EMAIL'])
              ->to('waledevtest@gmail.com')
              ->subject('🚨 ' . strtoupper($_ENV['LOGGER_NAME']) . ' Error Alert')
              ->html('<p>An error happened. Check logs for details.</p>');

            $emailHandler = new SymfonyMailerHandler($mailer, $email, $level);
            $emailHandler->setFormatter(new HtmlFormatter());

            $logger->pushHandler($emailHandler);
        }

        self::$logger = $logger; // Cache the logger

        return $logger;
    }

    /**
     * Get the cached logger instance.
     *
     * @return Logger
     *
     * @throws \InvalidArgumentException If logger has not been created
     */
    public static function getLogger(): Logger
    {
        if (self::$logger === null) {
            self::createWithMailer();
        }

        return self::$logger;
    }
}
