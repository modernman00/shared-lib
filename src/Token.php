<?php

declare(strict_types=1);

namespace Src;

use Src\{ToSendEmail, Select, Update, Utility,Exceptions\UnauthorisedException };
use Src\Exceptions\NotFoundException;

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
    public static function generateSendTokenEmail($data, $viewPath)
    {
        $id = $data['id'];
        // 1. check if email exists
        $email = Utility::checkInputEmail($data['email']);

        if (empty($email) || empty($id)) {
            throw new NotFoundException('Error : could not find email and id');
        }

        //2. generate token and update table
        $deriveToken = self::generateUpdateTableWithToken($email);

        $_SESSION['auth']['2FA_token_ts'] = time(); // Use 'auth' namespace
        $_SESSION['auth']['identifyCust'] = $id ?? 'TEST'; // Use 'auth' namespace
        $_SESSION['auth']['email'] = $email ?? 'TEST'; // Use 'auth' namespace
        //TODO send text to the user with the code

        //3. ACCOMPANY EMAIL CONTENT
        $emailData = ['code' => $deriveToken, 'email' => $email];

        $generateEmailArray = ToSendEmail::genEmailArray(
            viewPath: $viewPath,
            data: $emailData,
            subject: 'TOKEN'
        );

        ToSendEmail::sendEmailGeneral(
            $generateEmailArray,
            'member'
        );
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
     * MUST HAVE DB_TABLE_CODE_MGT in the .env file
     *
     * @return string|array|null|false
     *
     * @throws \Exception
     */
    public static function generateUpdateTableWithToken($email)
    {
        //5. generate code
        $code = self::generateAuthToken();

        // check if code is empty
        if (empty($code)) {
            throw new UnauthorisedException('Error : Could not generate token');
        }

        $table = $_ENV['DB_TABLE_CODE_MGT'];

        // check if table is empty

        if (empty($table)) {
            throw new UnauthorisedException('Error : Could not generate token');
        }

        // then check if the account is active on code_mgt table
        $query = Select::formAndMatchQuery(
            selection: 'SELECT_ONE',
            table: $table,
            identifier1: 'email'
        );
        $codeData = Select::selectFn2(query: $query, bind: [$email]);

        if (empty($codeData)) {

            // then insert the email into the code_mgt table
            $data = ['email' => $email, 'code' => $code];
            SubmitForm::submitForm($table, $data);
        } else {

            //6.  update login account table with the code
            $updateCodeToCustomer = new Update($table);
            $updateCodeToCustomer->updateTable('code', $code, 'email', $email);
            if (!$updateCodeToCustomer) {
                throw new UnauthorisedException('Error : Could not update token');
            }
        }


        return $code;
    }
/**
 * Checks if the token is valid
 * @param mixed $code
 * @return bool 
 */
    public static function verifyToken($code)
    {
        $code = Utility::checkInput($code);
        $email = cleanSession($_SESSION['auth']['email']);
        $query = Select::formAndMatchQuery(
            selection: 'SELECT_COL_DYNAMICALLY_ID_AND', 
            table: $_ENV['DB_TABLE_CODE_MGT'], 
            identifier1: 'email', 
            identifier2: 'code',
            colArray: ['code', 'email']
        );
        $data = Select::selectCountFn2(
            query: $query, 
            bind: [$email, $code]
        );
        if (!$data) {
            throw new UnauthorisedException('Cannot verify token');
        }

        return true;

    }
}
