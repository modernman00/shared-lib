<?php

declare(strict_types=1);

namespace Src\functionality;

use PDO;
use RuntimeException;
use Src\{
    CorsHandler,
    Db,
    LoginUtility,
    Recaptcha,
    SubmitForm,
    Transaction,
    Utility
};
use Src\functionality\middleware\FileUploadProcess;
use Src\functionality\middleware\GetRequestData;
use Src\functionality\SendEmailFunctionality;

/**
 * Class SubmitPostData
 *
 * Handles validated POST submissions with optional single/multiple image uploads.
 *
 * @method static mixed submitToOneTablenImage(string $table, ?array $minMaxData = null, ?array $removeKeys = null, string|array|null $fileName = null, ?string $imgPath = null, ?string $fileTable = null, ?array $newInput = null, bool $isCaptcha = true)
 * @method static mixed submitToMultipleTable(array $allowedTables, ?array $minMaxData = null, ?array $removeKeys = null, string|array|null $fileName = null, ?string $imgPath = null, ?string $fileTable = null, ?array $postData = null, bool $isCaptcha = true)
 * @method static mixed submitDataFileAndEmail(string $table, ?array $minMaxData = null, ?array $removeKeys = null, ?string $fileName = null, ?string $imgPath = null, ?string $fileTable = null, ?array $newInput = null, bool $isCaptcha = true, ?array $emailArray = null)
 */
class SubmitPostData
{
    // Default keys to remove from the payload before database insertion
    private const DEFAULT_REMOVE_KEYS = ['submit', 'button', 'token', 'g-recaptcha-response', 'grecaptcharesponse', 'siteKey', 'action'];

    /**
     * Centralized transaction wrapper to reduce try/catch repetition.
     */
    private static function handleTransaction(\Closure $callback)
    {
        try {
            $pdo = Db::connect2();
            Transaction::beginTransaction();
            $result = $callback($pdo);
            Transaction::commit();
            return $result;
        } catch (\Throwable $th) {
            Transaction::rollback();
            // Assuming showError is a global/utility function that handles the error response
            showError($th);
        }
    }

    /**
     * Sanitizes and processes the input data before DB interaction.
     * * @param array $input The raw request data.
     * @param ?array $minMaxData Validation constraints.
     * @param ?array $removeKeys Keys to remove.
     * @param ?array $newInput Additional data to merge.
     * @return array The cleaned and processed data.
     */
    private static function prepareData(
        array $input,
        ?array $minMaxData,
        ?array $removeKeys,
        ?array $newInput,
        ?array $optionalFields = null
    ): array {
        if (!empty($newInput)) {
            $input = array_merge($input, $newInput);
        }

        $sanitisedDataRaw = LoginUtility::getSanitisedInputData($input, $minMaxData, $optionalFields);
        // Assuming unsetPostData is a global/utility function
        $sanitisedData = \unsetPostData($sanitisedDataRaw, $removeKeys ?? self::DEFAULT_REMOVE_KEYS);

        // Assuming hashPasswordsInArray is a global/utility function
        return \hashPasswordsInArray($sanitisedData);
    }

    // --- Public Methods ---

    /**
     * Process and insert POST data into a single table after CAPTCHA validation.
     *
     * @param string $table Target table name.
     * @param ?array $minMaxData Optional per-field min/max length constraints.
     * @param ?array $removeKeys Keys to strip from POST data.
     * @param string|array|null $fileName Name or names of file input fields.
     * @param ?string $imgPath Relative directory path for image uploads.
     * @param ?string $fileTable Table name for storing image filename data.
     * @param ?array $newInput Optional new input data to be inserted.
     * @param bool $isCaptcha Whether to verify CAPTCHA.
     * @return mixed Returns last insert ID on success, or error response on failure.
     */
    public static function submitToOneTablenImage(
        string $table,
        ?array $minMaxData = null,
        ?array $removeKeys = null,
        string|array|null $fileName = null,
        ?string $imgPath = null,
        ?string $sourceFileTable = null,
        ?array $newInput = null,
        bool $isCaptcha = false,
        bool $isCaptchaV3 = false,
        string $captchaAction = 'SUBMIT',
        string $generalFileTable = 'images',
        ?array $optionalFields = null
    ): mixed {
        try {
            CorsHandler::setHeaders();

            $input = GetRequestData::getRequestData();

            // this is reCAPTCHA v3
            // this is reCAPTCHA v3
            if ($isCaptchaV3) {
                Recaptcha::verifyCaptchaEnterprise($input, $captchaAction);
                unset($input['action'], $input['siteKey']);
            } elseif ($isCaptcha) {
                // this is reCAPTCHA v2
                Recaptcha::verifyCaptcha($input);
            }
            $sanitisedData = self::prepareData($input, $minMaxData, $removeKeys, $newInput, $optionalFields);

            if ($fileName !== null) {
                $sanitisedData = self::processFileFields(
                    $sanitisedData,
                    $fileName,
                    $imgPath,
                    $sourceFileTable,
                    $generalFileTable,
                    false
                );
            }



            return self::handleTransaction(function (PDO $pdo) use ($table, $sanitisedData) {
                $lastId = SubmitForm::submitForm($table, $sanitisedData, $pdo);

                // Utility::msgSuccess MUST call exit() or die()
                // Utility::msgSuccess(201, 'Record created successfully', $lastId);
                return $lastId; // This return is technically unreachable if msgSuccess exits.
            });
        } catch (\Throwable $th) {
            showError($th);
            return false;
        }
    }

    /**
     * Process and insert POST data into multiple allowed tables in a single transaction.
     *
     * @param array $allowedTables Whitelisted table names eligible for insertion
     * @param ?array $minMaxData Optional per-field min/max length constraints
     * @param ?array $removeKeys Keys to strip from POST data
     * @param string|array|null $fileName File input field name or array of file input names.
     * @param ?string $imgPath Relative path to upload directory
     * @param ?string $fileTable Table name for image filenames
     * @param ?array $postData Optional POST data to override GetRequestData
     * @param bool $isCaptcha Whether to verify CAPTCHA
     * @return mixed Returns true on success, or error response on failure.
     */
    public static function submitToMultipleTable(
        array $allowedTables,
        ?array $minMaxData = null,
        ?array $removeKeys = null,
        string|array|null $fileName = null,
        ?string $imgPath = null,
        ?string $sourceFileTable = null,
        ?array $postData = null,
        bool $isCaptcha = false,
        bool $isCaptchaV3 = false,
        string $captchaAction = 'SUBMIT',
        string $generalFileTable = 'images',
        string $returnType = 'json',
        ?array $optionalFields = null
    ): mixed {

        try {
            CorsHandler::setHeaders();


            $input = $postData ?? GetRequestData::getRequestData();

            // this is reCAPTCHA v3
            // this is reCAPTCHA v3
            if ($isCaptchaV3) {
                Recaptcha::verifyCaptchaEnterprise($input, $captchaAction);
                unset($input['action'], $input['token']);
            } elseif ($isCaptcha) {
                // this is reCAPTCHA v2
                Recaptcha::verifyCaptcha($input);
            }
            $sanitisedData = self::prepareData($input, $minMaxData, $removeKeys, null, $optionalFields);

            if ($fileName !== null) {
                $sanitisedData = self::processFileFields(
                    $sanitisedData,
                    $fileName,
                    $imgPath,
                    $sourceFileTable,
                    $generalFileTable
                );
            }

            return self::handleTransaction(function (PDO $pdo) use ($sanitisedData, $allowedTables, $returnType) {
                self::insertMultipleTables($sanitisedData, $allowedTables, $pdo);
                // Utility::msgSuccess MUST call exit() or die()

                if ($returnType === 'json') {
                    Utility::msgSuccess(201, 'Records created successfully');
                    return true; // Unreachable if msgSuccess exits.

                } else {
                    return ['message' => 'Records created successfully'];
                }
            });
        } catch (\Throwable $th) {
            showError($th);
            return false;
        }
    }
    /**
     * Submits validated form data with optional file upload and email notification.
     *
     * ⚠️ This method is NOT transactional — no explicit DB begin/commit/rollback is performed.
     *
     * @param string $table               Target table for main data insertion.
     * @param ?array $minMaxData          Validation rules: ['min' => [...], 'max' => [...], 'data' => [...]].
     * @param ?array $removeKeys          Keys to exclude from submission payload.
     * @param string|array|null $fileName  File input field name or array of file field names (e.g., 'cv' or ['cv', 'bank_statements']).
     * @param ?string $imgPath            Upload destination path for file (e.g., 'resources/cv/').
     * @param ?string $fileTable          Table to store file metadata (e.g., filename, path).
     * @param ?array $newInput            Additional data to merge into submission (e.g., email config).
     * @param bool $isCaptcha             Whether to validate CAPTCHA before submission.
     * @param ?array $emailArray          Email config:
     *                                    - 'viewPath' => Blade view for email body
     *                                    - 'recipient' => 'admin' or 'member'
     *                                    - 'subject' => Email subject line
     *                                    - 'emailViewDataWithEmail' => ['firstName', 'lastName', 'email']
     *
     * @usage Example:
     *   $toCheck = [
     *     'min' => [2, 2, 2, 2],
     *     'max' => [15, 15, 35, 35],
     *     'data' => ['firstName', 'lastName', 'mobile', 'email']
     *   ];
     *
     *   $newInput = [
     *     'viewPath' => 'msg/newjobapp',
     *     'subject' => "$firstName $lastName sent an application",
     *     'recipient' => 'admin',
     *     'emailViewDataWithEmail' => [
     *       'firstName' => $firstName,
     *       'lastName' => $lastName,
     *       'email' => $_ENV['ADMIN_EMAIL']
     *     ]
     *   ];
     *
     *   $result = SubmitPostData::submitDataFileAndEmail(
     *     table: "applications",
     *     isCaptcha: false,
     *     minMaxData: $toCheck,
     *     fileName: 'cv',
     *     imgPath: 'resources/cv/',
     *     sourceFileTable: "applications",
     *     emailArray: $newInput,
     *     generalFileTable: "images"
     *   );
     *
     * @return mixed Returns last insert ID on success, or error response array on failure.
     */

    public static function submitDataFileAndEmail(
        string $table,
        ?array $minMaxData = null,
        ?array $removeKeys = null,
        string|array|null $fileName = null,
        ?string $imgPath = null,
        ?string $sourceFileTable = null,
        ?array $newInput = null,
        bool $isCaptcha = false,
        bool $isCaptchaV3 = false,
        string $captchaAction = 'SUBMIT',
        ?array $emailArray = null,
        string $generalFileTable = 'images',
        ?array $optionalFields = null
    ) {
        CorsHandler::setHeaders();

        try {
            $input = GetRequestData::getRequestData();
            // this is reCAPTCHA v3
            if ($isCaptchaV3) {
                Recaptcha::verifyCaptchaEnterprise($input, $captchaAction);
                unset($input['action'], $input['token']);
            } elseif ($isCaptcha) {
                // this is reCAPTCHA v2
                Recaptcha::verifyCaptcha($input);
            }

            $sanitisedData = self::prepareData($input, $minMaxData, $removeKeys, $newInput, $optionalFields);

            $filePath = null;
            $processedFileName = null;

            if ($fileName !== null) {
                foreach ((array) $fileName as $fieldName) {
                    if (!is_string($fieldName) || $fieldName === '') {
                        continue;
                    }

                    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
                        continue;
                    }

                    $fileData = $_FILES[$fieldName];
                    $hasUploadedFile = false;

                    if (isset($fileData['error'])) {
                        if (is_int($fileData['error'])) {
                            $hasUploadedFile = $fileData['error'] !== UPLOAD_ERR_NO_FILE;
                        } elseif (is_array($fileData['error'])) {
                            $hasUploadedFile = count(array_filter($fileData['error'], fn($error) => $error !== UPLOAD_ERR_NO_FILE)) > 0;
                        }
                    }

                    if (!$hasUploadedFile) {
                        continue;
                    }

                    $result = FileUploadProcess::process($sanitisedData, $sourceFileTable, $fieldName, $imgPath, $generalFileTable, false);
                    $sanitisedData = $result['sanitisedData'] ?? $sanitisedData;
                    $filePath = $result['filePath'] ?? $filePath;

                    if (isset($sanitisedData[$fieldName])) {
                        $processedFileName = $sanitisedData[$fieldName];
                    }
                }
            }

            $lastId = SubmitForm::submitForm($table, $sanitisedData);

            $fileContent = null;
            $displayFileName = null;

            if ($filePath !== null && $processedFileName !== null && file_exists($filePath)) {
                $fileContent = file_get_contents($filePath);
                $displayFileName = substr((string) $processedFileName, 11);
            }

            if ($emailArray !== null && $fileContent !== null && $displayFileName !== null) {
                SendEmailFunctionality::email(
                    $emailArray['viewPath'],
                    $emailArray['subject'],
                    $emailArray['emailViewDataWithEmail'],
                    $emailArray['recipient'],
                    $fileContent,
                    $displayFileName
                );
            }

            return $lastId;
        } catch (\Throwable $th) {
            // Note: No transaction rollback here, as none was started.
            showError($th);
        }
    }

    /**
     * Insert data into multiple whitelisted tables inside a single DB transaction.
     *
     * @param array $getTableData Associative array: tableName => data array
     * @param array $allowedTables Whitelist of permitted table names
     * @param PDO $pdo PDO connection object
     *
     * @throws RuntimeException If any insert fails
     */
    private static function processFileFields(
        array $sanitisedData,
        string|array $fileFields,
        ?string $imgPath,
        ?string $sourceFileTable,
        string $generalFileTable,
        bool $nested = true
    ): array {
        foreach ((array) $fileFields as $fieldName) {
            if (!is_string($fieldName) || $fieldName === '') {
                continue;
            }

            if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
                continue;
            }

            $fileData = $_FILES[$fieldName];
            $hasUploadedFile = false;

            if (isset($fileData['error'])) {
                if (is_int($fileData['error'])) {
                    $hasUploadedFile = $fileData['error'] !== UPLOAD_ERR_NO_FILE;
                } elseif (is_array($fileData['error'])) {
                    $hasUploadedFile = count(array_filter($fileData['error'], fn($error) => $error !== UPLOAD_ERR_NO_FILE)) > 0;
                }
            }

            if (!$hasUploadedFile) {
                continue;
            }

            $result = FileUploadProcess::process(
                $sanitisedData,
                $sourceFileTable,
                $fieldName,
                $imgPath,
                $generalFileTable,
                $nested
            );

            if (isset($result['sanitisedData']) && is_array($result['sanitisedData'])) {
                $sanitisedData = $result['sanitisedData'];
            }
        }

        return $sanitisedData;
    }

    private static function insertMultipleTables(array $getTableData, array $allowedTables, PDO $pdo): void
    {
        foreach ($getTableData as $tableName => $tableData) {
            // Use key_exists to check for $tableName in $getTableData if it's a map
            if (!in_array($tableName, $allowedTables, true)) {
                continue;
            }
            if (!SubmitForm::submitForm($tableName, $tableData, $pdo)) {
                // Throwing exception triggers the rollback in the handleTransaction wrapper.
                throw new RuntimeException("Failed to insert into table: {$tableName}");
            }
        }
    }
}
