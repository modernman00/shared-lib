<?php

declare(strict_types=1);

namespace Src\functionality;

use RuntimeException;
use Src\{
    CheckToken,
    CorsHandler,
    Db,
    FileUploader,
    Recaptcha,
    SubmitForm,
    Transaction,
    Utility
};

/**
 * SubmitwithSingleImg.
 *
 * A reusable utility class for handling POST data submission with optional image upload,
 * input sanitization, token validation, and multi-table support.
 *
 * 🔧 Core Responsibilities:
 * - Validate and sanitize incoming POST payloads
 * - Verify CAPTCHA and CSRF tokens
 * - Upload single or multiple images securely
 * - Insert sanitized data into one or more database tables
 *
 * 🧠 Usage Notes:
 * - Use `submitPostDataWithSingleImg()` for full flow including token and CAPTCHA checks
 * - Use `submitImgDataSingle()` or `submitImgDataMultiple()` to handle image uploads independently
 * - Use `insertMultipleTables()` if you need to insert into multiple tables in one transaction
 *
 * 💡 Designed for extensibility across blog posts, decisions, user records, and more.
 */
class SubmitPostData
{
    /**
     * Submits sanitized POST data to one or more tables after verifying CAPTCHA and token.
     *
     * @param array $multipleTablesnData Associative array of table names and their corresponding data payloads
     * @param array $cleanData Sanitized input data including 'token' and CAPTCHA fields
     *
     * @throws \Throwable If CAPTCHA fails, token is invalid, or any table insertion fails
     */
    public static function submit(array $multipleTablesnData, array $cleanData): void
    {
        CorsHandler::setHeaders();

        try {
            Recaptcha::verifyCaptcha($cleanData);
            $token = $cleanData['token'] ?? '';
            CheckToken::tokenCheck($token);
            self::insertMultipleTables($multipleTablesnData);
            Utility::msgSuccess(201, 'Record created successfully');
        } catch (\Throwable $th) {
            Transaction::rollback();
            showError($th);
        }
    }

    /**
     * Inserts data into multiple tables.
     *
     * @param array $getTableData Associative array where keys are table names and values are data arrays
     *
     * @throws RuntimeException If any table fails to insert
     */
    private static function insertMultipleTables(array $getTableData): void
    {
        // Logic to handle multiple table insertions
        // This could involve iterating over an array of table names or using a specific logic to determine the target table
        $pdo = Db::connect2();
        Transaction::beginTransaction();
        foreach ($getTableData as $tableName => $tableData) {
            if (!SubmitForm::submitForm($tableName, $tableData, $pdo)) {
                throw new RuntimeException("$tableName didn't submit");
            }
        }
        Transaction::commit();
    }

    /**
     * Uploads a single image file and returns the sanitized filename.
     *
     * @param string $formInputName Name of the file input field (e.g. 'image')
     * @param string $uploadPath Directory to store the uploaded image
     *
     * @return string Sanitized filename
     */
    public static function submitImgDataSingle($formInputName, $uploadPath): string
    {
        $fileName = FileUploader::fileUploadSingle($uploadPath, $formInputName, $_ENV['FILE_UPLOAD_CLOUDMERSIVE']);

        return $fileName;
    }

    /**
     * Uploads multiple image files and returns an array of sanitized filenames.
     *
     * @param string $formInputName Name of the file input field (e.g. 'images[]')
     * @param string $uploadPath Directory to store the uploaded images
     *
     * @return array Array of sanitized filenames
     */
    public static function submitImgDataMultiple($formInputName, $uploadPath): array
    {
        $fileName = FileUploader::fileUploadMultiple($uploadPath, $formInputName, $_ENV['FILE_UPLOAD_CLOUDMERSIVE']);

        return $fileName;
    }
}
