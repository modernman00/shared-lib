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
 * @method static mixed submitToOneTablenImage(string $table, ?array $minMaxData = null, ?array $removeKeys = null, ?string $fileName = null, ?string $imgPath = null, ?string $fileTable = null, ?array $newInput = null, bool $isCaptcha = true)
 * @method static mixed submitToMultipleTable(array $allowedTables, ?array $minMaxData = null, ?array $removeKeys = null, ?string $fileName = null, ?string $imgPath = null, ?string $fileTable = null, ?array $postData = null, bool $isCaptcha = true)
 * @method static mixed submitDataFileAndEmail(string $table, ?array $minMaxData = null, ?array $removeKeys = null, ?string $fileName = null, ?string $imgPath = null, ?string $fileTable = null, ?array $newInput = null, bool $isCaptcha = true, ?array $emailArray = null)
 */
class SubmitPostData
{
    // Default keys to remove from the payload before database insertion
    private const DEFAULT_REMOVE_KEYS = ['submit', 'button', 'token', 'g-recaptcha-response', 'grecaptcharesponse'];

    /**
     * Centralized transaction wrapper to reduce try/catch repetition.
     */
    private static function handleTransaction(\Closure $callback): mixed
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
            return \showError($th);
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
        ?array $newInput
    ): array {
        if (!empty($newInput)) {
            $input = array_merge($input, $newInput);
        }

        $sanitisedDataRaw = LoginUtility::getSanitisedInputData($input, $minMaxData);
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
     * @param ?string $fileName Name of the file input field.
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
        ?string $fileName = null,
        ?string $imgPath = null,
        ?string $sourceFileTable = null,
        ?array $newInput = null,
        bool $isCaptcha = true,
        string $generalFileTable = 'images'
    ): mixed {
        try {
        CorsHandler::setHeaders();

        $input = GetRequestData::getRequestData();

        if ($isCaptcha) {
            Recaptcha::verifyCaptcha($input);
        }

        $sanitisedData = self::prepareData($input, $minMaxData, $removeKeys, $newInput);

        // **File Handling Refactor:** Check if file key exists and has an uploaded file.
        // We assume a single file input OR a multiple input but only checking the first slot [0].
        if (isset($_FILES[$fileName]) && \is_array($_FILES[$fileName])&& !empty($_FILES[$fileName]['name'][0]) ) {
            $fileData = $_FILES[$fileName];

            // Check if it's a multiple-file array structure OR a single file structure
            $hasUploadedFile = (
                (isset($fileData['error'][0]) && $fileData['error'][0] !== 4) ||
                (isset($fileData['error']) && is_int($fileData['error']) && $fileData['error'] !== 4)
            );

            if ($hasUploadedFile) {
                // Assuming process handles single/multi file upload structure
                $sanitisedData = FileUploadProcess::process($sanitisedData, $sourceFileTable, $fileName, $imgPath, $generalFileTable, false);
            }

            $sanitisedData = $sanitisedData['sanitisedData'];
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
     * @param ?string $fileName File input field name (plural if multiple) 
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
        ?string $fileName = null,
        ?string $imgPath = null,
        ?string $sourceFileTable = null,
        ?array $postData = null,
        bool $isCaptcha = true,
        string $generalFileTable = 'images'
    ): mixed {

        try {
        CorsHandler::setHeaders();

        $input = $postData ?? GetRequestData::getRequestData();

        if ($isCaptcha) {
            Recaptcha::verifyCaptcha($input);
        }

        $sanitisedData = self::prepareData($input, $minMaxData, $removeKeys, null);

        // File handling for multiple tables / multiple files
        if ($fileName && isset($_FILES[$fileName]) && is_array($_FILES[$fileName])) {
            $fileData = $_FILES[$fileName];

            // Simplified check: check for any uploaded file at the first index (most common scenario)
            if (isset($fileData['error'][0]) && $fileData['error'][0] !== 4) {
                // Assuming process handles the upload and modifies $sanitisedData
                $sanitisedData = FileUploadProcess::process($sanitisedData, $sourceFileTable, $fileName, $imgPath, $generalFileTable);
            }
            $sanitisedData = $sanitisedData['sanitisedData'];
        }

                return self::handleTransaction(function (PDO $pdo) use ($sanitisedData, $allowedTables) {
                    self::insertMultipleTables($sanitisedData, $allowedTables, $pdo);
                    // Utility::msgSuccess MUST call exit() or die()
                    Utility::msgSuccess(201, 'Record created successfully');
            return true; // Unreachable if msgSuccess exits.
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
     * @param ?string $fileName           File input field name (e.g., 'cv').
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
        ?string $fileName = null,
        ?string $imgPath = null,
        ?string $sourceFileTable = null,
        ?array $newInput = null,
        bool $isCaptcha = true,
        ?array $emailArray = null,
        string $generalFileTable = 'images'
    ): mixed {
        CorsHandler::setHeaders();

        try {
            $input = GetRequestData::getRequestData();

            if ($isCaptcha) {
                Recaptcha::verifyCaptcha($input);
            }

            $sanitisedData = self::prepareData($input, $minMaxData, $removeKeys, $newInput);

            // **File Handling Refactor:** Guard against array offset on int error
            if ($fileName && isset($_FILES[$fileName]) && is_array($_FILES[$fileName])) {
                $fileData = $_FILES[$fileName];

                // Check for single-file upload error code (4 = UPLOAD_ERR_NO_FILE)
                if (isset($fileData['error']) && is_int($fileData['error']) && $fileData['error'] !== 4) {
                    // This is the simplest check for a single file upload structure
                    $result = FileUploadProcess::process($sanitisedData, $sourceFileTable, $fileName, $imgPath, $generalFileTable, false);

                    $sanitisedData = $result['sanitisedData'];
                    $filePath = $result['filePath'];
                }
            }

            $lastId = SubmitForm::submitForm($table, $sanitisedData);

            // get the file content 
            $fileContent = file_get_contents($filePath);
            $fileName = $sanitisedData["$fileName"];
            // Define the displayed name for the recipient (e.g., 'CV.docx')
            $displayFileName = substr($fileName, 11); // Removes the timestamp prefix

            // Email Functionality
            if ($emailArray !== null && $fileName !== null) {
                SendEmailFunctionality::email(
                    $emailArray['viewPath'],
                    $emailArray['subject'],
                    $emailArray['emailViewDataWithEmail'],
                    $emailArray['recipient'],
                    $fileContent ?? null, // Use null coalesce for safety
                    $displayFileName
                );
            }

            return $lastId;
        } catch (\Throwable $th) {
            // Note: No transaction rollback here, as none was started.
            return \showError($th);
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
