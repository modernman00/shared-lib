<?php 

namespace Src\Sanitise;


final class PregMatch
{
  public static function email(string $input): bool
  {
    return preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $input) === 1;
  }

  public static function password(string $input): bool
  {
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d]{8,}$/', $input) === 1;
  }

  // bad patterns

  public static function username(string $input): bool
  {
    return preg_match('/^[a-zA-Z0-9_]{3,}$/', $input) === 1;
  }

  // disallowed patterns in message body

  public static function message(string $input): bool
  {
    $error = [];

       // check if $cleanData['message'] contains a url or tinyurl.com 
            $bad_patterns = [
                '/tinyurl\.com/i',                          // Obfuscated link
                '/bit\.ly/i',                               // Another common shortener
                '/intimate photos/i',                       // Explicit bait
                '/nude/i',                                  // Common in adult spam
                '/http[s]?:\/\/[^\s]+/i',                   // Generic URL
                '/@lchaoge\.com$/i',                        // Known spam domain
                '/viagra/i',                                // Pharma spam
                '/cialis/i',                                // Pharma spam
                '/free money/i',                            // Scam bait
                '/claim your funds/i',                      // Scam bait
                '/inactive for \d+ days/i',                 // Urgency bait
                '/initiate a payout/i',                     // Scam bait
                '/support link/i',                          // Scam bait
                '/<script>/i',                              // XSS attempt
                '/<iframe>/i',                              // Embedded attack
                '/onerror=/i',                              // JS injection
                '/SELECT .* FROM/i',                        // SQL injection
                '/INSERT INTO/i',                           // SQL injection
                '/DROP TABLE/i',                            // SQL injection
                '/UNION SELECT/i',                          // SQL injection
                '/http:\/\/spam\.com/i',                    // Known spam domain
                '/http:\/\/www\.spam\.(com|net|org|ru|uk|us)/i', // Consolidated spam domains
                '/[A-Z]{6,}[a-z]{2,}/',                     // Randomized tokens
                '/\b(?:bitcoin|crypto|forex)\b/i',          // Financial scam bait
                '/\b(?:loan|debt relief|guaranteed approval)\b/i', // Financial scam bait
                '/\b(?:click here|act now|urgent)\b/i',     // Call-to-action spam
                '/\b(?:winner|congratulations|you\'ve won)\b/i', // Prize bait
                '/\b(?:unsubscribe|opt out)\b/i',           // Often spoofed in spam
                '/\b(?:adult|xxx|escort)\b/i',              // Adult content bait
                '/\b(?:miracle cure|secret formula)\b/i',   // Health scam bait
            ];


            foreach ($bad_patterns as $pattern) {
                if (preg_match($pattern, $input)) {

                    $error[] = "Your message contains a $pattern that is not allowed.";
                }
            }

            if (count($error) > 0) {
                return false;
            }

    return preg_match('/^[a-zA-Z0-9_]{3,}$/', $input) === 1;
  }
}