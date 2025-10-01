<?php

declare(strict_types=1);

namespace Src\functionality;

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

/**
 * Class SubmitPostData
 *
 * Handles validated POST submissions with optional single/multiple image uploads.
 *
 * **Core Responsibilities**
 * - Sanitize and validate incoming payloads
 * - Verify CAPTCHA and (optionally) CSRF token
 * - Upload and sanitise single or multiple image files
 * - Insert cleaned data into one or more database tables atomically
 *
 * **Usage**
 * - `submitToOneTablenImage()` → Full workflow for a single target table + optional single image
 * - `submitToMultipleTable()` → Workflow for inserting into multiple tables + multiple images
 * - `submitImgDataSingle()` / `submitImgDataMultiple()` → Stand‑alone upload handlers
 *
 * **Design Goals**
 * - Reusable across features like blogs, profiles, and content modules
 * - Clear flow for onboarding contributors — parameters describe expected structures
 * - Defensive patterns to prevent partial inserts or unsafe file handling
 * 
 * ENVIRONMENT VARIABLES:
 * - FILE_UPLOAD_CLOUDMERSIVE: Optional API key for virus scanning uploaded files
 * 
 * USAGE EXAMPLE:
 * ```php
 * $uploadDir = __DIR__ . '/../../public/images/uploads/';
 * SubmitPostData::submitToOneTablenImage(
 *     table: 'users',
 *     minMaxData: [
 *         'data' => ['email', 'password', 'username'],
 *         'min'  => [5, 8, 3],
 *         'max'  => [255, 64, 30]
 *     ],
 *     fileName: 'profile_image',
 *     imgPath: $uploadDir
 * );
 * ```
 */
class SubmitPostData
{
    /**
     * Process and insert POST data into a single table after CAPTCHA (and optional token) validation.
     *
     * @param string      $table        Target table name for insertion
     * @param array|null  $removeKeys   Keys to strip from POST data before insert (currently unused)
     * @param string|null $fileName     Name of the file input field and DB column for image filename
     * @param string|null $imgPath      Relative directory path for image uploads (must end with '/')
     * @param array|null  $minMaxData   Optional per‑field min/max length constraints [data=> ['email', 'password'], min => [3, 8], max => [255, 20]]
     * @param array|null  $newInput     Optional new input data to be inserted to the $input - example ['id' => 1, 'name' => 'John Doe']
     *
     * @throws \Throwable Rolls back transaction on any failure (validation, upload, DB insert, etc.)
     */
    public static function submitToOneTablenImage(
        string $table,
        ?array $minMaxData = null,
        ?array $removeKeys = null,
        ?string $fileName = null,
        ?string $imgPath = null,
        ?string $fileTable = null,
        ?array $newInput = null

    ): mixed {
        CorsHandler::setHeaders(); // set the header

        try {
            $input = GetRequestData::getRequestData();
            Recaptcha::verifyCaptcha($input);

            if(!empty($newInput)) {
                $input = array_merge($input, $newInput);
            }

            // Token check can be re‑enabled if CSRF validation is required
            $sanitisedDataRaw = LoginUtility::getSanitisedInputData($input, $minMaxData);
            $sanitisedData = unsetPostData($sanitisedDataRaw, $removeKeys);
            // check if isset password and hash it
            if (isset($sanitisedData['password'])) {
                $sanitisedData['password'] = \hashPassword($sanitisedData['password']);
            }


            $pdo = Db::connect2();
            Transaction::beginTransaction();

            // Attach uploaded filename if present
              if (!empty($_FILES)) {

               $sanitisedData = FileUploadProcess::process($sanitisedData, $fileTable, $fileName, $imgPath);
            }

            $lastId = SubmitForm::submitForm($table, $sanitisedData, $pdo);
            Transaction::commit();

            Utility::msgSuccess(201, 'Record created successfully', $lastId);
            return true;
        } catch (\Throwable $th) {
            Transaction::rollback();
            return showError($th);
        }
    }

    /**
     * Process and insert POST data into multiple allowed tables in a single transaction.
     * Optionally handles multiple image uploads.
     *
     * @param array       $allowedTables Whitelisted table names eligible for insertion
     * @param array|null  $removeKeys    Keys to strip from POST data before insert (currently unused)
     * @param array|null  $minMaxData    Optional per‑field min/max length constraints
     * @param string|null $fileName      File input field name (plural if multiple) 
     * @param string|null $imgPath       Relative path to upload directory
     * @param string|null $fileTable     Table name for image filenames
     * @param array|null $postData       Optional POST data to be inserted to the $input - example ['tableName' => ['id' => 1, 'name' => 'John Doe']]
     *
     * @throws \Throwable Rolls back on any validation, upload, or DB failure
     */
    public static function submitToMultipleTable(
        array $allowedTables,
        ?array $minMaxData = null,
        ?array $removeKeys = null,
        ?string $fileName = null,
        ?string $imgPath = null,
        ?string $fileTable = null,
        ?array $postData = null
    ): mixed {
        CorsHandler::setHeaders();

        try {
            if($postData !== null){
                $input = $postData;
            }else{
                $input = GetRequestData::getRequestData();
            }
            Recaptcha::verifyCaptcha($input);
            $sanitisedDataRaw = LoginUtility::getSanitisedInputData($input, $minMaxData);
            $sanitisedData = unsetPostData($sanitisedDataRaw, $removeKeys);
            $sanitisedData = hashPasswordsInArray($sanitisedData);
            if (!empty($_FILES)) {

               $sanitisedData = FileUploadProcess::process($sanitisedData, $fileTable, $fileName, $imgPath);
            }
            $pdo = Db::connect2();
            Transaction::beginTransaction();
            self::insertMultipleTables($sanitisedData, $allowedTables, $pdo);
            Transaction::commit();
            Utility::msgSuccess(201, 'Record created successfully');
            return true;
        } catch (\Throwable $th) {
            Transaction::rollback();
            return showError($th);
        }
    }

    /**
     * Insert data into multiple whitelisted tables inside a single DB transaction.
     *
     * @param array $getTableData  Associative array: tableName => data array
     * @param array $allowedTables Whitelist of permitted table names
     * @param \PDO $pdo PDO connection object
     *
     * @throws RuntimeException If any insert fails
     */
    private static function insertMultipleTables(array $getTableData, array $allowedTables, \PDO $pdo): void
    {


        foreach ($getTableData as $tableName => $tableData) {
            if (!in_array($tableName, $allowedTables, true)) {
                continue;
            }
            if (!SubmitForm::submitForm($tableName, $tableData, $pdo)) {
                throw new RuntimeException("Failed to insert into table: {$tableName}");
            }
        }
    }

   

}
