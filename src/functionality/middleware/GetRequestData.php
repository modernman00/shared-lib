<?php
namespace Src\functionality\middleware;

use Src\Exceptions\NotFoundException;

class GetRequestData
{
    /**
     * Retrieve and normalize incoming request payload.
     *
     * This method inspects the request's `Content-Type` header to determine how to
     * parse the body:
     *  - `application/json` → Decodes raw JSON payload into an associative array.
     *  - `multipart/form-data` → Merges form fields from $_POST with uploaded files from $_FILES.
     *  - Any other content type → Falls back to treating the payload as standard form-encoded ($_POST).
     *
     * Uploaded files are normalized to a predictable shape via normalize_files(),
     * ensuring consistent handling of single vs multiple file uploads across all contributors.
     *
     * If no valid data is found (empty array, non-array, or null), a NotFoundException is thrown.
     *
     * @return array Parsed request data with optional 'files' key.
     * @throws NotFoundException when no valid data is present.
     *
     * @developer
     *  - ✅ SAFE: Centralizes request parsing logic in one place, keeping controllers cleaner.
     *  - 📝 TODO (if needed): Implement stricter validation for supported content types.
     *  - ⚠️ ACTION: Consider sanitizing $data values here if this method feeds directly into business logic.
     *  - 🛡 SECURITY: Ensure normalize_files() includes file validation (size, MIME type, error handling).
     *  - 🔍 DEBUG: When onboarding new devs, point them here to understand request preprocessing.
     */
    public static function getRequestData(): array
    {
        // Capture the request's declared content type; fallback to empty string if missing.
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        // Default to an empty payload; will be filled based on parsing logic below.
        $data = [];

        if (stripos($contentType, 'application/json') !== false) {
            // Parse JSON body into an array; null-coalescing ensures $data is always an array.
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
        }
        elseif (stripos($contentType, 'multipart/form-data') !== false) {
            // Base form fields from $_POST
            $data = $_POST;

   
            if (!empty($_FILES)) {
                $data['files'] = $_FILES; // 🔍 
            }
        }
        else {
            // Fallback for application/x-www-form-urlencoded or unknown content types
            $data = $_POST;
        }

        // Fail fast if $data is empty, invalid, or not an array
        if (!$data || empty($data) || !is_array($data)) {
            throw new NotFoundException('There was no post data', 1);
        }

        return $data;
    }
}
