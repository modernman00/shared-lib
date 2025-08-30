<?php 

namespace Src\functionality;

use Src\ToSendEmail;

class SendEmail extends ToSendEmail {

 /**
 * Sends a templated email to a specified recipient type using dynamic view data.
 *
 * **Core Responsibilities**
 * - Prepares email payload using a view path and structured data
 * - Delegates sending logic to a general-purpose mail handler
 *
 * **Parameters**
 * @param string $viewPath Path to the email view/template (e.g. 'emails.contact')
 * @param string $subject Subject line for the email
 * @param array $emailViewDataWithEmail Associative array containing:
 *   - 'name'    => Sender's name
 *   - 'email'   => Sender's email address
 *   - 'message' => Message body content
 * @param string $recipient Target recipient type ('admin' or 'member')
 *
 * **Return**
 * @return void This method performs a side-effect (email dispatch) and returns nothing
 *
 * **Usage Example**
 * ```php
 * emailFn(
 *   viewPath: 'emails.contact',
 *   subject: 'New Contact Message',
 *   emailViewDataWithEmail: [
 *     'name' => 'Jane Doe',
 *     'email' => 'jane@example.com',
 *     'message' => 'Hello, I’d like to know more about your platform.'
 *   ],
 *   recipient: 'admin'
 * );
 * ```
 *
 * **Design Notes**
 * - Modular: separates view generation from sending logic
 * - Scalable: supports multiple recipient types and view templates
 * - Onboarding-safe: parameter structure is predictable and teachable
 */


  public static function emailFn(string $viewPath, string $subject, array $emailViewDataWithEmail, string $recipient) {

        $preparedEmailForSending = self::genEmailArray($viewPath, $emailViewDataWithEmail, $subject);
        self::sendEmailGeneral($preparedEmailForSending, $recipient);

  }
}