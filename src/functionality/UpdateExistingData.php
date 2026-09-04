<?php

declare(strict_types=1);

namespace Src\functionality;

use Src\{
    CorsHandler,
    LoginUtility,
    Recaptcha,
    Utility,
    Update,
    UpdateFn
};
use Src\functionality\middleware\FileUploadProcess;
use Src\functionality\middleware\GetRequestData;

/**
 * Handles validated updates to single or multiple database tables with optional file uploads.
 *
 * **Usage**
 * - `updateData()` → Full update workflow for a single table with optional file upload
 * - `updateMultipleTables()` → Workflow for updating multiple tables with optional file upload
 *
 * ENVIRONMENT VARIABLES:
 * - FILE_UPLOAD_CLOUDMERSIVE: Optional API key for virus scanning uploaded files
 *
 * USAGE EXAMPLE:
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
 * ```
 */
class UpdateExistingData
{
    /**
     * Handles validated updates to a single table, including optional image upload and password hashing.
     *
     * @param string      $table           Target table for update
     * @param mixed       $identifierValue Value used in WHERE clause to locate the row (e.g., user ID or email)
     * @param string      $identifier      Column name used to identify the row (default: 'id')
     * @param ?array      $minMaxData      Validation rules
     * @param ?array      $removeKeys      Keys to exclude from payload
     * @param string|array|null $fileName  File input field name
     * @param ?string     $imgPath         Upload destination path
     * @param ?string     $fileTable       Table to store file metadata
     * @param string      $generalFileTable Fallback table name for files
     * @param bool        $isRecaptcha     Whether to validate CAPTCHA
     * @param bool        $isCaptchaV3     Whether to use reCAPTCHA v3
     * @param string      $captchaAction   CAPTCHA action name
     * @param ?array      $postUpdateData  Additional data
     * @param string      $returnType      Return type
     * @param ?array      $optionalFields  Fields that are optional
     *
     * @return mixed
     */
    public static function updateData(
        string $table,
        mixed $identifierValue,
        string $identifier = 'id',
        ?array $minMaxData = null,
        ?array $removeKeys = null,
        string|array|null $fileName = null,
        ?string $imgPath = null,
        ?string $fileTable = null,
        string $generalFileTable = 'images',
        bool $isRecaptcha = false,
        bool $isCaptchaV3 = false, 
        string $captchaAction = 'UPDATE_DATA',
        ?array $postUpdateData = null,
        string $returnType = 'json',
        ?array $optionalFields = null
    ): mixed {
        CorsHandler::setHeaders();

        try {
            $input = $postUpdateData ? $postUpdateData : GetRequestData::getRequestData();
            // reCAPTCHA verification — skipped under PHPUnit (see isTestEnv()).
            if ($isCaptchaV3) {
                if (!isTestEnv()) {
                    Recaptcha::verifyCaptchaEnterprise($input, $captchaAction);
                }
                unset($input['action'], $input['siteKey']);
            } elseif ($isRecaptcha) {
                if (!isTestEnv()) {
                    Recaptcha::verifyCaptcha($input);
                }
            }

            // Token check can be re‑enabled if CSRF validation is required
            $sanitisedDataRaw = LoginUtility::getSanitisedInputData($input, $minMaxData, $optionalFields);           
            $sanitisedData = unsetPostData($sanitisedDataRaw, $removeKeys);
         
            // check if isset password and hash it
            if (isset($sanitisedData['password'])) {
                $sanitisedData['password'] = \hashPassword($sanitisedData['password']);
            }

            // Attach uploaded filename if present
            if ($fileName !== null) {
                $fileResult = FileUploadProcess::process($sanitisedData, $fileTable, $fileName, $imgPath, $generalFileTable, false);
                $sanitisedData = $fileResult['sanitisedData'] ?? $sanitisedData;
            }

            // if id is null set it to $identifierValue
            if (empty($sanitisedData[$identifier])) {
                $sanitisedData[$identifier] = $identifierValue;
            }

            // Update the record
            $update = new Update($table);
            $update->updateMultiplePOST($sanitisedData, $identifier);

            if ($returnType === 'json') {
                Utility::msgSuccess(200, 'Update was successful');
                return true;
            } else {
                return ['message' => 'Update was successful'];
            }
        } catch (\Throwable $th) {
            Utility::showError($th);
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
        string|array|null $fileName = null,
        ?string $imgPath = null,
        ?string $fileTable = null,
        string $generalFileTable = 'images',
        bool $isCaptcha = false,
        bool $isCaptchaV3 = false, 
        string $captchaAction = 'UPDATE_DATA',
        string $returnType = 'json',
        ?array $optionalFields = null
    ) {
        CorsHandler::setHeaders();

        try {
            if ($postData !== null) {
                $input = $postData;
            } else {
                $input = GetRequestData::getRequestData();
            }

            if ($isCaptchaV3) {
                if (!isTestEnv()) {
                    Recaptcha::verifyCaptchaEnterprise($input, $captchaAction);
                }
                unset($input['action'], $input['token']);
            } elseif ($isCaptcha) {
                if (!isTestEnv()) {
                    Recaptcha::verifyCaptcha($input);
                }
            }

            // Token check can be re‑enabled if CSRF validation is required
            $sanitisedDataRaw = LoginUtility::getSanitisedInputData($input, $minMaxData, $optionalFields);           
            $sanitisedData = unsetPostData($sanitisedDataRaw, $removeKeys);
         
            // check if isset password and hash it
            if (isset($sanitisedData['password'])) {
                $sanitisedData['password'] = \hashPassword($sanitisedData['password']);
            }

            // Attach uploaded filename if present
            if ($fileName !== null) {
                $fileResult = FileUploadProcess::process($sanitisedData, $fileTable, $fileName, $imgPath, $generalFileTable, true);
                $sanitisedData = $fileResult['sanitisedData'] ?? $sanitisedData;
            }

            // Update the tables
            UpdateFn::updateMultipleTables($sanitisedData, $allowedTables ?? [], $identifier, (string) $identifierValue);
        
            if ($returnType === 'json') {
                Utility::msgSuccess(200, 'Update was successful');
                return true;
            } else {
                return ['message' => 'Update was successful'];
            }
 
        } catch (\Throwable $th) {
            Utility::showError($th);
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
