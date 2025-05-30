<?php

namespace Src;


use Src\Update;
use Src\Utility;
use Src\CheckToken;
use Src\ToSendEmail;
use Src\Exceptions\HttpException;

class Token extends CheckToken
{
  public static function generateSendTokenEmail($data)
  {
    $id = $data['id'];
    // 1. check if email exists 
    $email = Utility::checkInputEmail($data['email']);

    //2. generate token and update table
    $deriveToken = self::generateUpdateTableWithToken($id);
    //TODO send text to the user with the code

    //3. ACCOMPANY EMAIL CONTENT             
    $emailData = ['token' => $deriveToken, 'email' => $email];
    $generateEmailArray = ToSendEmail::genEmailArray(viewPath: "msg/customer/token", data: $emailData, subject: "TOKEN");

    ToSendEmail::sendEmailWrapper(var: $generateEmailArray, recipientType: 'member');
  }
  /**
   * to generate random byte - token
   *
   * @throws \Exception 
   */
  public static function generateAuthToken(): string
  {
    return mb_strtoupper(bin2hex(random_bytes(6)));
  }
  /**
   * Helps to generate token, and it updates the login table as well
   * @param mixed $customerId 
   * @return string|array|null|false 
   * @throws \Exception 
   */
  public static function generateUpdateTableWithToken($customerId)
  {
    //5. generate code
    $token = self::generateAuthToken();
    //6.  update login account table with the code
    $updateCodeToCustomer = new Update('account');
    $updateCodeToCustomer->updateTable('token', $token, 'id', $customerId);
    if (!$updateCodeToCustomer) {
      throw new HttpException("Could not update token");
    }
    $_SESSION['auth']['2FA_token_ts'] = time(); // Use 'auth' namespace
    $_SESSION['auth']['identifyCust'] = $customerId ?? "TEST"; // Use 'auth' namespace
    return $token;
  }
}
