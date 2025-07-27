<?php

declare(strict_types=1);

namespace Src;

use Src\Exceptions\NotFoundException;

/**
 * Dynamically serve an image file from a specified/protected subfolder instead of the public folder
 * 🧪 How to Use It Dynamically In your router or controller handler:
 * $serve = new ServeImgController();
 * $serve->serve($folder, $img);
 * Then call it like: $url =  /serveImage.php?folder=passport&img=photo123.jpg
 *  $data['staffShareCodeImg'] = $url;
 * <p><a href="{{ $data['staffShareCodeImg'] }}" target="_blank">View the Right to Work Document</a></p> <p><img src="{{ $data['staffShareCodeImg'] }}" alt="{{ $data['carerName'] }}'s Right to Work Document" style="max-width: 100%; height: auto; border: 1px solid #ccc;"></p>.
 * baseDir is the base directory of the image root folder (adjust as needed) - source from $_ENV['ASSET_DIR']
 * allowedFolders is the whitelist of allowed subfolders (adjust as needed) - source from $_ENV['ALLOWED_FOLDERS'] example ['images', 'avatars', 'photos']
 * allowedExtensions is the whitelist of allowed file extensions (adjust as needed) - source from $_ENV['ALLOWED_EXTENSIONS'] example ['jpg', 'jpeg', 'png', 'gif']
 
 
 */
class ServeImg
{
    protected string $baseDir;
    protected array $allowedFolders;
    protected array $allowedExtensions;

public function __construct()
{
    // Define the base image root folder (adjust as needed)
    $this->baseDir = $_ENV['ASSET_DIR'] ?? throw new NotFoundException('Asset directory not available');
    // Optional: whitelist of allowed subfolders
    $this->allowedFolders = $_ENV['ALLOWED_FOLDERS'] ?? throw new NotFoundException('Allowed folders not available');
    // Optional: allowed extensions
    $this->allowedExtensions = $_ENV['ALLOWED_EXTENSIONS'] ?? throw new NotFoundException('Allowed extensions not available');
}


    /**
     * Dynamically serve an image file from a specified subfolder.
     *
     * @param string $folder Subdirectory under baseDir (e.g., 'passport')
     * @param string $imgName File name (e.g., 'user123.jpg')
     */
    public function serve(string $folder, string $imgName): void
    {
        if (empty($folder) || empty($imgName)) {
              throw new \InvalidArgumentException('Folder and filename are required.');
        }

        // Security: Check if folder is allowed
        if (!in_array($folder, $this->allowedFolders)) {
            throw new \InvalidArgumentException('Access to this folder is not allowed.');
        }

        // Security: prevent path traversal
        $safeName = basename($imgName);

        // Validate file extension
        $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
        if (!in_array($ext, $this->allowedExtensions)) {
            throw new \InvalidArgumentException('Unsupported file type.');
        }

        $filePath = $this->baseDir . $folder . '/' . $safeName;

        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException('File not found.');
        }

        // Detect MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

             // Caching headers
        $lastModified = gmdate('D, d M Y H:i:s', filemtime($filePath)) . ' GMT';
        header('Last-Modified: ' . $lastModified);
        header('Cache-Control: public, max-age=86400');

        // Serve image with headers
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($filePath));
        header('Content-Disposition: inline; filename="' . $safeName . '"');
        readfile($filePath);
        exit;
    }
}
