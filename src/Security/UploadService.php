<?php

namespace App\Security;

use Exception;

class UploadService
{
    private const MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5MB
    private const MAX_DIMENSION = 1280; // 1280px max width/height
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    public static function processUpload(array $fileUpload, string $targetDir): array
    {
        if (!isset($fileUpload['error']) || $fileUpload['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload failed with error code: " . ($fileUpload['error'] ?? 'unknown'));
        }

        if ($fileUpload['size'] > self::MAX_SIZE_BYTES) {
            throw new Exception("File exceeds maximum allowed size of 5 MB.");
        }

        $tmpPath = $fileUpload['tmp_name'];
        if (!is_uploaded_file($tmpPath) && !file_exists($tmpPath)) {
            throw new Exception("Invalid uploaded file.");
        }

        // Validate real MIME type via finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        if (!array_key_exists($realMime, self::ALLOWED_MIME_TYPES)) {
            throw new Exception("Invalid file type: Only JPEG, PNG and WebP images are allowed.");
        }

        // Re-encode and resize with GD
        $image = null;
        switch ($realMime) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($tmpPath);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($tmpPath);
                break;
            case 'image/webp':
                $image = @imagecreatefromwebp($tmpPath);
                break;
        }

        if (!$image) {
            throw new Exception("Failed to decode image data. File may be corrupted or disguised.");
        }

        $origWidth = imagesx($image);
        $origHeight = imagesy($image);

        // Calculate resize dimensions (max 1280px on longest edge)
        $newWidth = $origWidth;
        $newHeight = $origHeight;

        if ($origWidth > self::MAX_DIMENSION || $origHeight > self::MAX_DIMENSION) {
            if ($origWidth >= $origHeight) {
                $newWidth = self::MAX_DIMENSION;
                $newHeight = (int)round(($origHeight / $origWidth) * self::MAX_DIMENSION);
            } else {
                $newHeight = self::MAX_DIMENSION;
                $newWidth = (int)round(($origWidth / $origHeight) * self::MAX_DIMENSION);
            }
        }

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG/WebP if applicable
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
        imagealphablending($resized, true);

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        // Save with cryptographically random filename outside web root
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0750, true);
        }

        $randomName = bin2hex(random_bytes(16)) . '.jpg';
        $destinationPath = rtrim($targetDir, '/') . '/' . $randomName;

        // Save as standard clean JPEG with quality 85
        $saved = imagejpeg($resized, $destinationPath, 85);

        imagedestroy($image);
        imagedestroy($resized);

        if (!$saved) {
            throw new Exception("Failed to save re-encoded image.");
        }

        return [
            'file_name' => $randomName,
            'original_name' => htmlspecialchars(basename($fileUpload['name']), ENT_QUOTES, 'UTF-8'),
            'mime_type' => 'image/jpeg',
            'file_size' => filesize($destinationPath)
        ];
    }
}
