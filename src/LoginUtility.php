<?php

declare(strict_types=1);

namespace Src;

use Src\Exceptions\BadRequestException;
use Src\Exceptions\NotFoundException;
use Src\Exceptions\UnauthorisedException;
use Src\Sanitise\Sanitise;

class LoginUtility
{
    /**
     * @param mixed $inputData this is the form data
     * @param mixed $databaseData this must be database data that already has the database password
     *
     * @return true
     *
     * @throws \Exception
     */
    public static function checkPassword(#[SensitiveParameter] array $inputData, #[SensitiveParameter] array $databaseData): bool
    {
        $textPassword = $inputData['password'];
        $dbPassword = $databaseData['password'];
        $id = $databaseData['id'];
        $table = $_ENV['DB_TABLE_LOGIN'];
        $options = ['cost' => 12];

        if (password_verify($textPassword, $dbPassword) === false) {

            LoginUtility::logAudit(null, $inputData['email'], 'failed', $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

            LoginUtility::checkSuspiciousActivity($inputData['email'], $_SERVER['REMOTE_ADDR']);

            throw new UnauthorisedException('There is a problem with your login credential! - Password');
        }

        // After successful verification, it checks if the stored password hash is outdated (e.g., algorithm changed or cost parameter updated).
        // If so, it updates the password hash in the database with a new, more secure hash.

        if (password_needs_rehash($dbPassword, PASSWORD_DEFAULT, $options)) {
            // If so, create a new hash, and replace the old one
            $newHash = password_hash($textPassword, PASSWORD_DEFAULT, $options);

            $data = ['password' => $newHash, 'id' => $id];
            $passUpdate = new AllFunctionalities();
            $result = $passUpdate->updateMultiplePOST($data, $table, 'id');

            if (!$result) {
                throw new UnauthorisedException('Password could not be updated');
            }
        }

        return true;
    }

    /**
     * @param mixed $inputData form data as a array $inputData['email']
     *
     * @return array
     *
     * @throws \Exception
     */
    public static function useEmailToFindData($inputData)
    {
        $email = $inputData['email'];

        $query = Select::formAndMatchQuery(selection: 'SELECT_ONE', table: 'account', identifier1: 'email');
        $emailData = Select::selectFn2(query: $query, bind: [$email]);

        if (empty($emailData)) {

            LoginUtility::logAudit(null, $email, 'failed', $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

            LoginUtility::checkSuspiciousActivity($email, $_SERVER['REMOTE_ADDR']);

            throw new UnauthorisedException('We do not recognise your account');
       
        }

        return $emailData[0];
    }

    /**
     * @param mixed $inputData form data as a $email
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public static function checkIfEmailExist(string $email): mixed
    {
        $query = Select::formAndMatchQuery(selection: 'SELECT_COL_ID', table: 'account', identifier1: 'email', column: 'email');
        $data = Select::selectCountFn2(query: $query, bind: [$email]);

        if (!$data) {
            throw new NotFoundException('We cannot find your email');
        }
        foreach ($data as $data);

        return $data;
    }

    /**
     * @param string $col the first column could be "id"
     * @param string $col2 the second column, could be "status'
     * @param array $data , could be the postdata but must have a email
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public static function findTwoColUsingEmail(string $col, string $col2, array $data): mixed
    {
        $outcome = self::useEmailToFindData($data);
        $colOne = $outcome["$col"];
        $colTwo = $outcome["$col2"];

        $query = Select::formAndMatchQuery(selection: 'SELECT_TWO_COLS_ID', table: 'account', identifier1: 'email', column: $col, column2: $col2);
        $result = Select::selectFn2(query: $query, bind: [$colOne, $colTwo]);

        if (!$result) {
            throw new NotFoundException('opps!We cannot locate the information');
        }
        foreach ($result as $data);

        return $data;
    }

    /**
     * @param string $col the first column could be "id"
     * @param array $data , could be the postdata but must have a email
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public static function findOneColUsingEmail(string $col, array $data): mixed
    {
        $email = $data['email'];

        $query = Select::formAndMatchQuery(selection: 'SELECT_COL_ID', table: 'account', identifier1: 'email', column: $col);

        $data = Select::selectFn2(query: $query, bind: [$email]);

        if (!$data) {
            throw new NotFoundException('can\'t locate the information');
        }
        foreach ($data as $data);

        return $data;
    }

    /**
     * sanitise and get the clean data.
     *
     * @param mixed $inputData - $_post or form input
     * @param mixed $minMaxData - set metric for min and max and the input name(data) you want to check
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public static function getSanitisedInputData(array $inputData, $minMaxData = null)
    {
        $sanitise = new Sanitise($inputData, $minMaxData);
        $sanitisedData = $sanitise->getCleanData();
        $error = $sanitise->errors;
        if ($error) {
            $theError = 'There is a problem with your input<br>' . implode('; <br>', $error);
            throw new BadRequestException($theError);
        }

        return $sanitisedData;
    }

    /**
     * to generate random byte - token.
     *
     * @throws \Exception
     */
    public static function generateAuthToken(): string
    {
        return mb_strtoupper(bin2hex(random_bytes(4)));
    }

    /**
     * Helps to generate token, and it updates the login table as well.
     *
     * @param mixed $customerId
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
            throw new UnauthorisedException('Error : Could not update token');
        }
        $_SESSION['2FA_token_ts'] = time();
        $_SESSION['identifyCust'] = $customerId ?? 'TEST';

        return $token;
    }

        /**
     * find a user on the database  .
     *
     * @param mixed $email
     *
     * @return array
     *
     * @throws \Exception
     */
    public static function findUserByEmailPassword(string $email,string $password): array
    {
        $query = Select::formAndMatchQuery(
            selection: 'SELECT_COUNT_TWO', 
            table: $_ENV['DB_TABLE_LOGIN'], 
            identifier1: 'email', 
            identifier2: 'password',
            limit: 'LIMIT 1'
        );
        $data = Select::selectCountFn2(query: $query, bind: [$email, $password]);
        if (!$data) {
            throw new NotFoundException('We cannot locate the information');
        }
        return $data;
    }

      /**
     * Log login attempts (success or failure)
     */
    public static function logAudit(?int $userId, string $email, string $status, string $ip, string $userAgent): void
    {
        $data = [
            'user_id'    => $userId,
            'email'      => $email,
            'status'     => $status,
            'ip_address' => $ip,
            'user_agent' => $userAgent
        ];

        SubmitForm::submitForm($_ENV['DB_TABLE_LOGIN_AUDIT'], $data);
    }

    public static function checkSuspiciousActivity(string $email, string $ip): void
    {
        $stmt = Db::connect2()->prepare("
            SELECT COUNT(*) as attempts FROM audit_logs
            WHERE email = :email AND status = 'failure' AND timestamp > (NOW() - INTERVAL 10 MINUTE)
        ");
        $stmt->execute([':email' => $email]);
        $count = (int)$stmt->fetchColumn();

        if ($count >= 5) {


            // create email data 
            $emailData = [
                'email'      => $email,
                'attempts'   => $count,
                'ip_address' => $ip,
            ];
            $viewPath = $_ENV['SUSPICIOUS_ALERT'] ?? 'msg/admin/suspicious';

            $emailData = ToSendEmail::genEmailArray(viewPath: $viewPath, data: $emailData, subject: 'Suspicious Activity Alert');

            ToSendEmail::sendEmailGeneral($emailData, 'admin');
        }
    }

}

