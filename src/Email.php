<?php

namespace Src;

class Email
{
  private string $to;
  private string $subject;
  private string $body;
  private string $from;
  private array $cc = [];
  private array $bcc = [];
  private array $attachments = [];
  private string $replyTo = '';
  private array $customHeaders = [];
  private bool $isHtml = false;
  private string $charset = 'UTF-8';
  private int $priority = 3; // 1 = High, 3 = Normal, 5 = Low
  private ?string $lastError = null;

  public function __construct(string $to, string $subject, string $body, string $from = '')
  {
    $this->to = $this->sanitizeEmail($to);
    $this->subject = $this->sanitizeHeader($subject);
    $this->body = $this->sanitizeBody($body);
    $this->from = $from ? $this->sanitizeEmail($from) : (getenv('DEFAULT_FROM_EMAIL') ?: 'no-reply@example.com');
  }

  // Getters (5 methods)
  public function getTo(): string
  {
    return $this->to;
  }

  public function getSubject(): string
  {
    return $this->subject;
  }

  public function getBody(): string
  {
    return $this->body;
  }

  public function getFrom(): string
  {
    return $this->from;
  }

  public function getLastError(): ?string
  {
    return $this->lastError;
  }

  // Setters (6 methods)
  public function setTo(string $to): self
  {
    $this->to = $this->sanitizeEmail($to);
    return $this;
  }

  public function setSubject(string $subject): self
  {
    $this->subject = $this->sanitizeHeader($subject);
    return $this;
  }

  public function setBody(string $body): self
  {
    $this->body = $this->sanitizeBody($body);
    return $this;
  }

  public function setFrom(string $from): self
  {
    $this->from = $this->sanitizeEmail($from);
    return $this;
  }

  public function setHtml(bool $isHtml): self
  {
    $this->isHtml = $isHtml;
    return $this;
  }

  public function setPriority(int $priority): self
  {
    if ($priority < 1 || $priority > 5) {
      throw new \InvalidArgumentException('Priority must be between 1 (High) and 5 (Low)');
    }
    $this->priority = $priority;
    return $this;
  }

  // Configuration Methods (4 methods)
  public function addCc(array $cc): self
  {
    foreach ($cc as $email) {
      $this->cc[] = $this->sanitizeEmail($email);
    }
    return $this;
  }

  public function addBcc(array $bcc): self
  {
    foreach ($bcc as $email) {
      $this->bcc[] = $this->sanitizeEmail($email);
    }
    return $this;
  }

  public function addAttachments(array $attachments): self
  {
    foreach ($attachments as $file) {
      if (!is_readable($file)) {
        throw new \InvalidArgumentException("Attachment file not readable: $file");
      }
      $this->attachments[] = $file;
    }
    return $this;
  }

  public function setReplyTo(string $replyTo): self
  {
    $this->replyTo = $this->sanitizeEmail($replyTo);
    return $this;
  }

  // Core Functionality (3 methods)
  public function send(): bool
  {
    if (!$this->isValidEmail($this->to)) {
      throw new \InvalidArgumentException("Invalid 'To' email address: {$this->to}");
    }
    if ($this->from && !$this->isValidEmail($this->from)) {
      throw new \InvalidArgumentException("Invalid 'From' email address: {$this->from}");
    }

    $headers = $this->buildHeaders();
    $message = $this->buildMessage();

    // Use PHP's mail() function for sending
    $success = mail($this->to, $this->subject, $message, $headers);

    if (!$success) {
      $this->lastError = 'Failed to send email using mail() function';
      error_log("Email sending failed to {$this->to}: {$this->lastError}");
    }

    return $success;
  }

  public function isValidEmail(string $email): bool
  {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
  }

  public function __toString(): string
  {
    return sprintf(
      "To: %s\nSubject: %s\nFrom: %s\nCC: %s\nBCC: %s\n\n%s",
      $this->to,
      $this->subject,
      $this->from,
      implode(', ', $this->cc),
      implode(', ', $this->bcc),
      $this->body
    );
  }

  // Private Helper Methods (2 methods)
  private function buildHeaders(): string
  {
    $headers = [];
    if ($this->from) {
      $headers[] = "From: {$this->from}";
    }
    if ($this->replyTo) {
      $headers[] = "Reply-To: {$this->replyTo}";
    }
    if (!empty($this->cc)) {
      $headers[] = "Cc: " . implode(', ', $this->cc);
    }
    if (!empty($this->bcc)) {
      $headers[] = "Bcc: " . implode(', ', $this->bcc);
    }
    $headers[] = "X-Priority: {$this->priority}";
    $headers[] = "MIME-Version: 1.0";
    $headers[] = $this->isHtml ? "Content-Type: text/html; charset={$this->charset}" : "Content-Type: text/plain; charset={$this->charset}";

    return implode("\r\n", $headers);
  }

  private function buildMessage(): string
  {
    if (empty($this->attachments)) {
      return $this->body;
    }

    $boundary = uniqid('boundary_');
    $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";

    $message = "--{$boundary}\r\n";
    $message .= $this->isHtml
      ? "Content-Type: text/html; charset={$this->charset}\r\n"
      : "Content-Type: text/plain; charset={$this->charset}\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $this->body . "\r\n";

    foreach ($this->attachments as $file) {
      $content = chunk_split(base64_encode(file_get_contents($file)));
      $filename = basename($file);
      $message .= "--{$boundary}\r\n";
      $message .= "Content-Type: application/octet-stream; name=\"{$filename}\"\r\n";
      $message .= "Content-Transfer-Encoding: base64\r\n";
      $message .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
      $message .= $content . "\r\n";
    }

    $message .= "--{$boundary}--";
    return $message;
  }
}
