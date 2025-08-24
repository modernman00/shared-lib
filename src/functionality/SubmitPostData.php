<?php

declare(strict_types=1);

namespace Src\functionality;

use RuntimeException;
use Src\{
    CorsHandler,
    Db,
    LoginUtility,
    FileUploader,
    Recaptcha,
    SubmitForm,
    Transaction,
    Utility
};
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
     *
     * @throws \Throwable Rolls back transaction on any failure (validation, upload, DB insert, etc.)
     */
    public static function submitToOneTablenImage(
        string $table,
        ?array $minMaxData = null,
        ?array $removeKeys = null,
        ?string $fileName = null,
        ?string $imgPath = null

    ): void {
        CorsHandler::setHeaders();

        try {
            $input = GetRequestData::getRequestData();
            Recaptcha::verifyCaptcha($input);

            // Token check can be re‑enabled if CSRF validation is required
            $sanitisedData = LoginUtility::getSanitisedInputData($input, $minMaxData);

            if ($removeKeys) {
                self::unsetPostData($sanitisedData, $removeKeys);
            }


            $pdo = Db::connect2();
            Transaction::beginTransaction();

            // Attach uploaded filename if present
            if (!empty($sFile)) {
                $getProcessedFileName = self::submitImgDataSingle(

                    $fileName,
                    $imgPath
                );
                $sanitisedData[$fileName] = $getProcessedFileName;
            }

            SubmitForm::submitForm($table, $sanitisedData, $pdo);
            Transaction::commit();

            Utility::msgSuccess(201, 'Record created successfully');
        } catch (\Throwable $th) {
            Transaction::rollback();
            showError($th);
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
     *
     * @throws \Throwable Rolls back on any validation, upload, or DB failure
     */
    public static function submitToMultipleTable(
        array $allowedTables,
        ?array $minMaxData = null,
        ?array $removeKeys = null,
        ?string $fileName = null,
        ?string $imgPath = null
    ): void {
        CorsHandler::setHeaders();

        try {
            $input = GetRequestData::getRequestData();
            Recaptcha::verifyCaptcha($input);
            $sanitisedData = LoginUtility::getSanitisedInputData($input, $minMaxData);

            if ($removeKeys) {
                self::unsetPostData($sanitisedData, $removeKeys);
            }

            if (!empty($_FILES)) {
                $getProcessedFileName = self::submitImgDataMultiple(
                    $fileName,
                    $imgPath
                );

                $fileArr = [];
                // Map each uploaded file to a unique column name
                foreach ($getProcessedFileName as $key => $value) {
                    $imgColumnName = $fileName . ($key + 1);
                    $fileArr[$imgColumnName] = $value;
                }
            }
            $sanitisedData[$fileName] =  $fileArr ?? null;

            self::insertMultipleTables($sanitisedData, $allowedTables);
            Utility::msgSuccess(201, 'Record created successfully');
        } catch (\Throwable $th) {
            Transaction::rollback();
            showError($th);
        }
    }

    /**
     * Insert data into multiple whitelisted tables inside a single DB transaction.
     *
     * @param array $getTableData  Associative array: tableName => data array
     * @param array $allowedTables Whitelist of permitted table names
     *
     * @throws RuntimeException If any insert fails
     */
    private static function insertMultipleTables(array $getTableData, array $allowedTables): void
    {
        $pdo = Db::connect2();
        Transaction::beginTransaction();

        foreach ($getTableData as $tableName => $tableData) {
            if (!in_array($tableName, $allowedTables, true)) {
                continue;
            }
            if (!SubmitForm::submitForm($tableName, $tableData, $pdo)) {
                throw new RuntimeException("Failed to insert into table: {$tableName}");
            }
        }

        Transaction::commit();
    }

    /**
     * Upload a single image and return the sanitized filename.
     *
     * @param string $formInputName HTML file input field name
     * @param string $uploadPath    Destination directory path
     * @param mixed  $sFile         Raw file array from request
     *
     * @return string Sanitized filename (spaces removed, validated)
     */
    public static function submitImgDataSingle($formInputName, $uploadPath): string
    {
        $fileName = FileUploader::fileUploadSingle(
            $uploadPath,
            $formInputName
        );
        return Utility::checkInputImage(str_replace(' ', '', $fileName));
    }

    /**
     * Upload multiple images and return an array of sanitized filenames.
     *
     * @param string $formInputName HTML file input field name (e.g. 'images[]')
     * @param string $uploadPath    Destination directory path
     * @param array  $postData      Full POST data array containing file data
     *
     * @return array|null Sanitized filenames, or null if no files were uploaded
     */
    public static function submitImgDataMultiple(string $formInputName, string $uploadPath): mixed
    {
        return FileUploader::fileUploadMultiple(
            $uploadPath,
            $formInputName
        );
    }

    private function unsetPostData(array $data, ?array $removeKeys = null): array
    {
        if (!empty($removeKeys)) {
            foreach ($removeKeys as $key) {
                unset($data[$key]);
            }
        }
        return $data;
    }
}
