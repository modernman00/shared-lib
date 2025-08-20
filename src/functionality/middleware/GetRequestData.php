<?php 

namespace Src\functionality\middleware;


class GetRequestData
{

// What this does:

//     Figures out the incoming content type.

//     For JSON: decodes it straight into an associative array.

//     For multipart: merges $_POST with a _files entry holding the raw $_FILES superglobal.

//     Leaves every byte of validation, sanitation, and file‑moving to the calling code.

  public static function getRequestData(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $data = [];

    if (stripos($contentType, 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
    }
    elseif (stripos($contentType, 'multipart/form-data') !== false) {
        $data = $_POST;
        // Attach raw $_FILES array without inspection
        if (!empty($_FILES)) {
            $data['files'] = $_FILES;
        }
    }
    else {
        $data = $_POST; // fallback
    }

    return $data;
}

}