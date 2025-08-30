<?php

declare(strict_types=1);

namespace Src\functionality;

use Src\{
    CorsHandler,
    LoginUtility,
    FileUploader,
    Recaptcha,
    Utility,
    Update
};
use Src\functionality\middleware\GetRequestData;

/**
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
class UpdateExistingData
{
    /**
     *  * - `updateData()` → Handles validated updates to a single table, including optional image upload and password hashing
     *   - Uses `$identifier` to locate the row to update (e.g., 'id', 'email', 'mobile')
     *   - Automatically injects `$identifierValue` if missing from POST payload
     * @param mixed       $identifierValue Value used in WHERE clause to locate the row (e.g., user ID or email)
     * @param string      $identifier      Column name used to identify the row (default: 'id')
     *
     * - If `$sanitisedData[$identifier]` is missing, it will be set to `$identifierValue` before update
     * - Ensures contributor-safe fallback for PATCH-like behavior

     * * UPDATE USAGE EXAMPLE:
     * ```php
     * $userId = $_SESSION['user_id'];
     * UpdateExistingData::updateData(
     *     table: 'users',
     *     identifierValue: $userId,
     *     identifier: 'id',
     *     minMaxData: [
     *         'data' => ['username', 'bio'],
     *         'min'  => [3, 0],
     *         'max'  => [30, 500]
     *     ],
     *     fileName: 'avatar',
     *     imgPath: __DIR__ . '/../../public/images/uploads/'
     * );
     * 
     */
    public static function updateData(
        string $table,
        mixed $identifierValue,
        string $identifier = 'id',
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
            $sanitisedDataRaw = LoginUtility::getSanitisedInputData($input, $minMaxData);

              if ($removeKeys) {
                $sanitisedData = self::unsetPostData($sanitisedDataRaw, $removeKeys);
            }

            // REMOVE TOKEN AS IT NOT NO LONGER NEEDED

            $sanitisedData = self::unsetPostData($sanitisedData, ['token']);

            // check if isset password and hash it
            if (isset($sanitisedData['password'])) {
                $sanitisedData['password'] = \hashPassword($sanitisedData['password']);
            }


            // Attach uploaded filename if present
            if (!empty($_FILES)) {
                $getProcessedFileName = self::submitImgDataSingle(
                    $fileName,
                    $imgPath
                );
                $sanitisedData[$fileName] = $getProcessedFileName;
            }

            // if id is null set it to $identiferValue
            if (empty($sanitisedData[$identifier])) {
                $sanitisedData[$identifier] = $identifierValue;
            }

            // Update the blog next
            $update = new Update($table);
            $update->updateMultiplePOST($sanitisedData, $identifier);

            Utility::msgSuccess(200, 'Update was successful');
        } catch (\Throwable $th) {

            showError($th);
        }
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
    private static function submitImgDataSingle($formInputName, $uploadPath): string
    {
        $fileName = FileUploader::fileUploadSingle(
            $uploadPath,
            $formInputName
        );
        return Utility::checkInputImage(str_replace(' ', '', $fileName));
    }

    private static function unsetPostData(array $data, array $keysToRemove): array
    {
        foreach ($data as $key => $value) {
            // Remove key if it matches
            if (in_array($key, $keysToRemove, true)) {
                unset($data[$key]);
                continue;
            }

            // If value is an array, recurse
            if (is_array($value)) {
                $data[$key] = self::unsetPostData($value, $keysToRemove);
            }
        }
        return $data;
    }
}
