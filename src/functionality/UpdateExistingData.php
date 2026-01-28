<?php

declare(strict_types=1);

namespace Src\functionality;

use Src\{
    CorsHandler,
    LoginUtility,
    FileUploader,
    Recaptcha,
    Utility,
    Update,
    UpdateFn
};
use Src\functionality\middleware\FileUploadProcess;
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
        ?string $imgPath = null,
        ?string $fileTable = null,
        string $generalFileTable = 'images',
        string $isRecaptcha = 'true',
        bool $isCaptchaV3 = false, 
        string $captchaAction = 'UPDATE_DATA',
        ?array $postUpdateData = null

    ): mixed {
        CorsHandler::setHeaders();

        try {
            $input = $postUpdateData ? $postUpdateData : GetRequestData::getRequestData();
                // this is reCAPTCHA v3
          if ($isRecaptcha && $isCaptchaV3) {
                $token = $input['recaptchaTokenV3'];
                Recaptcha::verifyCaptchaV3($token, $captchaAction);
            }elseif ($isRecaptcha) {
                // this is reCAPTCHA v2
                Recaptcha::verifyCaptcha($input);
            }

            // Token check can be re‑enabled if CSRF validation is required
            $sanitisedDataRaw = LoginUtility::getSanitisedInputData($input, $minMaxData);           
            $sanitisedData = unsetPostData($sanitisedDataRaw, $removeKeys);
         
            // check if isset password and hash it
            if (isset($sanitisedData['password'])) {
                $sanitisedData['password'] = \hashPassword($sanitisedData['password']);
            }

            // Attach uploaded filename if present
              $sanitisedData = FileUploadProcess::process($sanitisedData, $fileTable, $fileName, $imgPath, $generalFileTable, false);

            // if id is null set it to $identiferValue
            if (empty($sanitisedData[$identifier]) || $sanitisedData[$identifier] === null) {
                $sanitisedData[$identifier] = $identifierValue;
            }

             $sanitisedDataNew = $sanitisedData['sanitisedData'];

            // Update the blog next
            $update = new Update($table);
            $update->updateMultiplePOST($sanitisedDataNew, $identifier);

            Utility::msgSuccess(200, 'Update was successful');
            return true;
        } catch (\Throwable $th) {

            return showError($th);
            return false;
        }
    }


   public static function updateMultipleTables(
             mixed $identifierValue,
        string $identifier = 'id',
        ?array $postData = null,
        ?array $allowedTables = null,
        ?array $minMaxData = null,
        ?array $removeKeys = null,
        ?string $fileName = null,
        ?string $imgPath = null,
        ?string $fileTable = null,
        string $generalFileTable = 'images',
         string $isCaptcha = 'true',
        bool $isCaptchaV3 = false, 
        string $captchaAction = 'UPDATE_DATA',

    ): mixed {
        CorsHandler::setHeaders();

        try {
               if($postData !== null){
                $input = $postData;
            }else{
                $input = GetRequestData::getRequestData();
            }
            Recaptcha::verifyCaptcha($input);
            if ($isCaptcha && $isCaptchaV3) {
                $token = $input['recaptchaTokenV3'];
                Recaptcha::verifyCaptchaV3($token, $captchaAction);
            }elseif ($isCaptcha) {
                // this is reCAPTCHA v2
                Recaptcha::verifyCaptcha($input);
            }
          

            // Token check can be re‑enabled if CSRF validation is required
            $sanitisedDataRaw = LoginUtility::getSanitisedInputData($input, $minMaxData);           
            $sanitisedData = unsetPostData($sanitisedDataRaw, $removeKeys);
         
            // check if isset password and hash it
            if (isset($sanitisedData['password'])) {
                $sanitisedData['password'] = \hashPassword($sanitisedData['password']);
            }

            // Attach uploaded filename if present
            $sanitisedData = FileUploadProcess::process($sanitisedData, $fileTable, $fileName, $imgPath, $generalFileTable);

            // // if id is null set it to $identiferValue
            // if (empty($sanitisedData[$identifier]) || $sanitisedData[$identifier] === null ) {
            //     $sanitisedData[$identifier] = $identifierValue;
            // }

            // Update the blog next
            UpdateFn::updateMultipleTables($sanitisedData, $allowedTables, $identifier, $identifierValue);
        

            Utility::msgSuccess(200, 'Update was successful');
            return true;
        } catch (\Throwable $th) {

            return showError($th);
        }
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
