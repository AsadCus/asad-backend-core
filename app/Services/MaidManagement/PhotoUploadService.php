<?php

namespace App\Services\MaidManagement;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

/**
 * Service untuk handle photo upload dari dokumen
 * Best Practices:
 * - Gunakan Laravel Storage untuk konsistensi
 * - Validasi image sebelum save
 * - Generate unique filename
 * - Support multiple disk (local, s3, etc)
 * - Clean up temporary files
 */
class PhotoUploadService
{
    private string $disk;
    private string $directory;
    private array $allowedMimeTypes;
    private int $maxFileSize;

    public function __construct(?string $disk = null, ?string $directory = null)
    {
        // Use config values or fallback to provided values
        $this->disk = $disk ?? config('maid_photo.storage_disk', 'public');
        $this->directory = $directory ?? config('maid_photo.storage_directory', 'maid_photos');
        $this->allowedMimeTypes = config('maid_photo.allowed_mime_types', [
            'image/jpeg', 'image/png', 'image/jpg', 'image/webp'
        ]);
        $this->maxFileSize = config('maid_photo.max_file_size', 5 * 1024 * 1024);
    }

    /**
     * Upload photo dari binary content
     * 
     * @param string $imageContent Binary content dari gambar
     * @param string $originalFilename Original filename untuk reference
     * @return array ['success' => bool, 'path' => string, 'url' => string, 'error' => string]
     */
    public function uploadFromBinary(string $imageContent, string $originalFilename): array
    {
        try {
            // Validasi content
            if (empty($imageContent)) {
                throw new Exception('Image content is empty');
            }

            // Detect image type
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($imageContent);

            if (!in_array($mimeType, $this->allowedMimeTypes)) {
                throw new Exception("Invalid image type: {$mimeType}. Allowed: " . implode(', ', $this->allowedMimeTypes));
            }

            // Check file size
            $fileSize = strlen($imageContent);
            if ($fileSize > $this->maxFileSize) {
                throw new Exception("Image too large: " . round($fileSize / 1024 / 1024, 2) . "MB. Max: " . ($this->maxFileSize / 1024 / 1024) . "MB");
            }

            // Generate unique filename
            $filename = $this->generateFilename($originalFilename, $mimeType);
            $filePath = $this->directory . '/' . $filename;

            // Save to storage
            Storage::disk($this->disk)->put($filePath, $imageContent);

            // Generate URL - handle different disk types
            $url = $this->generateUrl($filePath);

            return [
                'success' => true,
                'path' => $filePath,
                'url' => $url,
                'size' => $fileSize,
                'mime_type' => $mimeType,
                'error' => null
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'path' => null,
                'url' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Convert dan optimize image menggunakan GD atau Imagick
     * 
     * @param string $imageContent
     * @param string $format Target format (jpeg, png, webp)
     * @param int $quality Quality 1-100
     * @return string Converted image content
     */
    public function convertAndOptimize(string $imageContent, string $format = 'jpeg', int $quality = 85): string
    {
        try {
            // Try Imagick first (better quality)
            if (class_exists('Imagick')) {
                return $this->convertWithImagick($imageContent, $format, $quality);
            }

            // Fallback to GD
            return $this->convertWithGD($imageContent, $format, $quality);

        } catch (Exception $e) {
            // Return original if conversion fails
            return $imageContent;
        }
    }

    /**
     * Convert menggunakan Imagick
     */
    private function convertWithImagick(string $imageContent, string $format, int $quality): string
    {
        $img = new \Imagick();
        $img->readImageBlob($imageContent);
        
        // Resize jika terlalu besar (max 1200px width)
        $width = $img->getImageWidth();
        if ($width > 1200) {
            $img->scaleImage(1200, 0);
        }

        // Set format dan quality
        $img->setImageFormat($format);
        $img->setImageCompressionQuality($quality);

        // Strip metadata untuk reduce file size
        $img->stripImage();

        $result = $img->getImageBlob();
        $img->destroy();

        return $result;
    }

    /**
     * Convert menggunakan GD
     */
    private function convertWithGD(string $imageContent, string $format, int $quality): string
    {
        $image = imagecreatefromstring($imageContent);
        if (!$image) {
            throw new Exception('Failed to create image from string');
        }

        ob_start();
        switch ($format) {
            case 'jpeg':
            case 'jpg':
                imagejpeg($image, null, $quality);
                break;
            case 'png':
                // PNG quality is 0-9, convert from 0-100
                $pngQuality = round((100 - $quality) / 10);
                imagepng($image, null, $pngQuality);
                break;
            case 'webp':
                imagewebp($image, null, $quality);
                break;
            default:
                imagejpeg($image, null, $quality);
        }
        $result = ob_get_clean();
        imagedestroy($image);

        return $result;
    }

    /**
     * Generate unique filename dengan timestamp dan hash
     */
    private function generateFilename(string $originalFilename, string $mimeType): string
    {
        $extension = $this->getExtensionFromMimeType($mimeType);
        $baseName = pathinfo($originalFilename, PATHINFO_FILENAME);
        
        // Sanitize filename
        $baseName = Str::slug($baseName);
        
        // Generate unique name: maid_photo_{basename}_{timestamp}_{hash}.{ext}
        $timestamp = now()->format('YmdHis');
        $hash = substr(md5($originalFilename . $timestamp), 0, 8);
        
        return "maid_photo_{$baseName}_{$timestamp}_{$hash}.{$extension}";
    }

    /**
     * Get extension dari mime type
     */
    private function getExtensionFromMimeType(string $mimeType): string
    {
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        return $extensions[$mimeType] ?? 'jpg';
    }

    /**
     * Delete photo dari storage
     */
    public function delete(string $path): bool
    {
        try {
            if (Storage::disk($this->disk)->exists($path)) {
                return Storage::disk($this->disk)->delete($path);
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Validate apakah file adalah valid image
     */
    public function validateImage(string $imageContent): array
    {
        $errors = [];

        if (empty($imageContent)) {
            $errors[] = 'Image content is empty';
            return ['valid' => false, 'errors' => $errors];
        }

        // Check file size
        $fileSize = strlen($imageContent);
        if ($fileSize > $this->maxFileSize) {
            $errors[] = "Image too large: " . round($fileSize / 1024 / 1024, 2) . "MB";
        }

        // Check mime type
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($imageContent);
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            $errors[] = "Invalid image type: {$mimeType}";
        }

        // Check if valid image using GD
        $image = @imagecreatefromstring($imageContent);
        if (!$image) {
            $errors[] = 'Invalid or corrupted image data';
        } else {
            imagedestroy($image);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'mime_type' => $mimeType ?? null,
            'size' => $fileSize
        ];
    }

    /**
     * Generate URL untuk file berdasarkan disk type
     */
    private function generateUrl(string $filePath): string
    {
        // For public disk, use asset helper
        if ($this->disk === 'public') {
            return asset('storage/' . $filePath);
        }

        // For other disks that support url() method (s3, etc)
        try {
            $filesystem = Storage::disk($this->disk);
            if (method_exists($filesystem, 'url')) {
                return $filesystem->url($filePath);
            }
        } catch (Exception $e) {
            // Fall back to storage path
        }

        // Fallback
        return url('storage/' . $filePath);
    }
}
