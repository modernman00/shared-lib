<?php

namespace Src;

/**
 * Dynamically serve an image file from a specified/protected subfolder instead of the public folder
 * 🧪 How to Use It Dynamically In your router or controller handler:
 * $serve = new ServeImgController(); 
 * $serve->serve($folder, $img);
 * Then call it like: $url =  /serveImage.php?folder=passport&img=photo123.jpg
 *  $data['staffShareCodeImg'] = $url;
 * <p><a href="{{ $data['staffShareCodeImg'] }}" target="_blank">View the Right to Work Document</a></p> <p><img src="{{ $data['staffShareCodeImg'] }}" alt="{{ $data['carerName'] }}'s Right to Work Document" style="max-width: 100%; height: auto; border: 1px solid #ccc;"></p>
 * 

 */

class ServeImg
{
    // Define the base image root folder (adjust as needed)
    protected string $baseDir = __DIR__ . '/../../resources/asset/';

    // Optional: whitelist of allowed subfolders
    protected array $allowedFolders = ['images', 'avatars', 'photos'];

    // Optional: allowed extensions
    protected array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

    /**
     * Dynamically serve an image file from a specified subfolder
     * @param string $folder Subdirectory under baseDir (e.g., 'passport')
     * @param string $imgName File name (e.g., 'user123.jpg')
     */
    public function serve(string $folder, string $imgName): void
    {
        if (empty($folder) || empty($imgName)) {
            http_response_code(400);
            echo 'Folder and filename are required.';
            exit;
        }

        // Security: Check if folder is allowed
        if (!in_array($folder, $this->allowedFolders)) {
            http_response_code(403);
            echo 'Access to this folder is not allowed.';
            exit;
        }

        // Security: prevent path traversal
        $safeName = basename($imgName);

        // Validate file extension
        $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
        if (!in_array($ext, $this->allowedExtensions)) {
            http_response_code(415); // Unsupported Media Type
            echo 'Unsupported file type.';
            exit;
        }

        $filePath = $this->baseDir . $folder . '/' . $safeName;

        if (!file_exists($filePath)) {
            http_response_code(404);
            echo 'File not found.';
            exit;
        }

        // Detect MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        // Serve image with headers
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}
