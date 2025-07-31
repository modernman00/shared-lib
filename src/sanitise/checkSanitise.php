<?php

declare(strict_types=1);

namespace Src\Sanitise;


use Src\{
    Select,
    Token,
    Update
};
use Src\Exceptions\{HttpException, NotFoundException, ValidationException, DatabaseException};

class CheckSanitise
{
    /**
     * @param mixed $inputData this is the form data
     * @param mixed $databaseData this must be database data that already has the database password
     *
     * @return true
     *
     * @throws \Exception
     */
    public static function checkPassword(#[SensitiveParameter] array $inputData, #[SensitiveParameter] array $databaseData, int $bcryptCost = 12): bool
    {
        $textPassword = $inputData['password'];
        $dbPassword = $databaseData['password'];
        $id = $databaseData['id'];
        $table = $_ENV['DB_TABLE_LOGIN'] ?? 'account' ?? 'login';


        // Validate input data
        if (!isset($inputData['password']) || !is_string($inputData['password'])) {
            throw new \InvalidArgumentException('Invalid or missing password in input data');
        }


        // Check if rehashing is needed
        if (password_needs_rehash($dbPassword, PASSWORD_BCRYPT, ['cost' => $bcryptCost])) {
            $newHash = password_hash($textPassword, PASSWORD_BCRYPT, ['cost' => $bcryptCost]);
            if ($newHash === false) {
                throw new HttpException('Failed to generate new password hash', 500);
            }

            // Update password hash in a transaction
            try {
                $update = new Update($table);
                $result = $update->updateTable('password', $newHash, 'id', $id);
                if (!$result) {
                    throw new DatabaseException('Failed to update password');
                }
            } catch (\PDOException $e) {
                showError($e);
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
            throw new NotFoundException('We cannot locate the information');
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
            throw new NotFoundException('We cannot locate the information');
        }

        // foreach ($data as $data);
        return $data[0];
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
            throw new NotFoundException('We cannot locate the information');
        }

        // foreach ($result as $data);
        return $data[0];
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
            throw new NotFoundException('We cannot locate the information');
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
        $error = $sanitise->getErrors();
        if ($error) {
            $theError = 'There is a problem with your input<br>' . implode('; <br>', $error);
            throw new ValidationException($theError);
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
        return mb_strtoupper(bin2hex(random_bytes(6)));
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
        $token = Token::generateAuthToken();
        //6.  update login account table with the code
        $updateCodeToCustomer = new Update('account');
        $updateCodeToCustomer->updateTable('token', $token, 'id', $customerId);
        if (!$updateCodeToCustomer) {
            throw new HttpException('Could not update token');
        }
        $_SESSION['auth']['2FA_token_ts'] = time(); // Use 'auth' namespace
        $_SESSION['auth']['identifyCust'] = $customerId ?? 'TEST'; // Use 'auth' namespace

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
            identifier2: 'password'
        );
        $data = Select::selectFn2(query: $query, bind: [$email, $password]);
        return $data[0] ?? [];
    }
}
