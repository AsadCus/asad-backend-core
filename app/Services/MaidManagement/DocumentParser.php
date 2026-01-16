<?php

namespace App\Services\MaidManagement;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Handles document parsing (PDF, DOCX)
 * Responsibility: Parse uploaded files and extract raw text
 */
class DocumentParser
{
    private array $parsers;

    public function __construct(array $parsers)
    {
        $this->parsers = $parsers;
    }

    /**
     * Parse uploaded document to text
     * 
     * @throws \Exception if file type not supported or parsing fails
     */
    public function parse(UploadedFile $file): string
    {
        $this->ensureTempDirectory();
        
        $storedPath = $file->store('temp_uploads', 'local');
        $fullPath = $this->resolveFilePath($storedPath, $file);
        $extension = strtolower($file->getClientOriginalExtension());

        try {
            $text = $this->parseByExtension($extension, $fullPath);

            return $text;
        } finally {
            // Always cleanup temp file
            Storage::disk('local')->delete($storedPath);
        }
    }

    /**
     * Parse document dan extract photos sekaligus
     * 
     * @param UploadedFile $file
     * @return array ['text' => string, 'photos' => array]
     * @throws \Exception if file type not supported or parsing fails
     */
    public function parseWithPhotos(UploadedFile $file): array
    {
        $this->ensureTempDirectory();
        
        $storedPath = $file->store('temp_uploads', 'local');
        $fullPath = $this->resolveFilePath($storedPath, $file);
        $extension = strtolower($file->getClientOriginalExtension());

        try {
            $text = $this->parseByExtension($extension, $fullPath);
            $photos = $this->extractPhotos($extension, $fullPath);

            return [
                'text' => $text,
                'photos' => $photos
            ];
        } finally {
            // Always cleanup temp file
            Storage::disk('local')->delete($storedPath);
        }
    }

    /**
     * Extract photos dari document
     */
    private function extractPhotos(string $extension, string $filePath): array
    {
        // Only DOCX support photo extraction for now
        if ($extension === 'docx' && isset($this->parsers['docx'])) {
            return $this->parsers['docx']->extractAllPhotos($filePath);
        }

        return [];
    }

    private function ensureTempDirectory(): void
    {
        $tempDir = storage_path('app' . DIRECTORY_SEPARATOR . 'temp_uploads');
        
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
    }

    private function resolveFilePath(string $storedPath, UploadedFile $file): string
    {
        $fullPath = Storage::disk('local')->path($storedPath);

        // Fallback to uploaded temp path if stored path not found
        if (!file_exists($fullPath)) {
            $tmp = $file->getRealPath();
            if ($tmp && file_exists($tmp)) {
                $fullPath = $tmp;
            }
        }

        return $fullPath;
    }

    private function parseByExtension(string $extension, string $filePath): string
    {
        if ($extension === 'pdf' && isset($this->parsers['pdf'])) {
            return $this->parsers['pdf']->parse($filePath);
        }
        
        if ($extension === 'docx' && isset($this->parsers['docx'])) {
            return $this->parsers['docx']->parse($filePath);
        }

        throw new \Exception("Unsupported file type: {$extension}");
    }
}
