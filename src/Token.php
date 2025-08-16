<?php

declare(strict_types=1);

namespace Src;

use Src\Exceptions\HttpException;
use Src\{ToSendEmail, Select, Update, Utility, Exceptions\NotFoundException, Exceptions\UnauthorisedException };

class Token extends CheckToken
{/**
 * Helps to generate token, aπnd it updates the login table as well.
 * two sessions are set $_SESSION['auth']['2FA_token_ts'] and $_SESSION['auth']['identifyCust']
 * @param mixed $data 
 * @param string $viewPath 
 * @return void 
 * @throws \Exception 
 * @throws \Throwable 
 * @throws \InvalidArgumentException 
 */
    public static function generateSendTokenEmail($data, $viewPath = 'msg/customer/token')
    {
        $id = $data['id'];
        // 1. check if email exists
        $email = Utility::checkInputEmail($data['email']);

        //2. generate token and update table
        $deriveToken = self::generateUpdateTableWithToken($id);

        $_SESSION['auth']['2FA_token_ts'] = time(); // Use 'auth' namespace
        $_SESSION['auth']['identifyCust'] = $customerId ?? 'TEST'; // Use 'auth' namespace
        //TODO send text to the user with the code

        //3. ACCOMPANY EMAIL CONTENT
        $emailData = ['token' => $deriveToken, 'email' => $email];
        $generateEmailArray = ToSendEmail::genEmailArray(viewPath: $viewPath, data: $emailData, subject: 'TOKEN');

        ToSendEmail::sendEmailWrapper(var: $generateEmailArray, recipientType: 'member');
    }

    /**
     * to generate random byte - token.
     *
     * @throws \Exception
     */
    public static function generateAuthToken(): string
    {
        return mb_strtoupper(bin2hex(random_bytes(6)));
    }

    /**
     * Helps to generate token, aπnd it updates the login table as well.
     *
     * @param mixed $customerId
     * session
     *
     * @return string|array|null|false
     *
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
            throw new HttpException('Could not update token');
        }
       

        return $token;
    }
/**
 * Checks if the token is valid
 * @param mixed $code
 * @return void 
 */
    public static function verifyToken($code)
    {
        $id = $_SESSION['auth']['identifyCust'];
        $code = checkInput($code);
        $query = Select::formAndMatchQuery(
            selection: 'SELECT_COL_DYNAMICALLY_ID_AND', 
            table: $_ENV['DB_TABLE_LOGIN'], 
            identifier1: 'id', 
            identifier2: 'code',
            colArray: ['code', 'email']
        );
        $data = Select::selectCountFn2(
            query: $query, 
            bind: [$id, $code]
        );
        if (!$data) {
            throw new UnauthorisedException('Cannot verify token');
        }

    }
}
