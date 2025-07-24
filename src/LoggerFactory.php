<?php


namespace Src;

use Monolog\Level;
use Monolog\Logger;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\SymfonyMailerHandler;

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
   * @throws \Exception If logger creation fails
   * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface If email transport fails
   * @throws \Symfony\Component\Mailer\Exception\LogicException If email configuration is incorrect
   * @throws \Symfony\Component\Mailer\Exception\InvalidArgumentException If email arguments are invalid
   * @throws \Symfony\Component\Mailer\Exception\RuntimeException If email sending fails
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

    // Normalize log path
    $logPath = $_ENV['LOGGER_PATH'];
    if (!str_starts_with($logPath, '/')) {
      $logPath = __DIR__ . '/' . ltrim($logPath, '/');
    }

    // Write logs to file with rotation ( 7 days)
    $fileHandler = new RotatingFileHandler($logPath, 7, Level::Debug);
    $logger->pushHandler($fileHandler);

    // ✉️ Send email alerts for error or more severe

    $transport = Transport::fromDsn($_ENV['MAILER_DSN']);
    $mailer = new Mailer($transport);

    $email = (new Email())
      ->from($_ENV['USER_EMAIL'])
      ->to('waledevtest@gmail.com')
      ->subject('🚨 ' . strtoupper($_ENV['LOGGER_NAME']) . ' Error Alert')
      ->html('<p>An error happened. Check logs for details.</p>');

    $emailHandler = new SymfonyMailerHandler($mailer, $email, $level);

    $logger->pushHandler($emailHandler);


    self::$logger = $logger; // Cache the logger
    return $logger;
  }

  /**
     * Get the cached logger instance.
     *
     * @return Logger
     * @throws \InvalidArgumentException If logger has not been created
     */
    public static function getLogger(): Logger
    {
        if (self::$logger === null) {
            throw new \InvalidArgumentException('Logger not initialized. Call createWithMailer first.');
        }
        return self::$logger;
    }
}
