<?php

namespace App\Services\MaidManagement;

use Illuminate\Http\UploadedFile;
use App\Services\MaidManagement\Mappers\ReferenceDataMapper;
use App\Services\MaidManagement\Mappers\FormDataMapper;

/**
 * Main orchestrator for document upload and parsing
 * Responsibility: Coordinate parsing, extraction, and mapping process
 */
class MaidDocumentUploadService
{
    private DocumentParser $documentParser;
    private DataExtractionService $extractionService;
    private FormDataMapper $formMapper;

    public function __construct(
        DocumentParser $documentParser,
        DataExtractionService $extractionService,
        FormDataMapper $formMapper
    ) {
        $this->documentParser = $documentParser;
        $this->extractionService = $extractionService;
        $this->formMapper = $formMapper;
    }

    /**
     * Process uploaded document and return form-ready data
     * 
     * @param UploadedFile $file
     * @param bool $autoUploadPhotos Whether to automatically upload extracted photos
     * @return array ['success' => true, 'data' => [...], 'metadata' => [...], 'photos' => [...]]
     * @throws \Exception if parsing or extraction fails
     */
    public function process(UploadedFile $file, bool $autoUploadPhotos = true): array
    {
        // Step 1: Parse document to text and extract photos
        $parseResult = $this->documentParser->parseWithPhotos($file);
        $text = $parseResult['text'];
        $extractedPhotos = $parseResult['photos'];

        // Step 2: Auto-upload photos if enabled
        $uploadedPhotos = [];
        $photoUrl = null;
        
        if ($autoUploadPhotos && !empty($extractedPhotos)) {
            $uploadResult = $this->uploadPhotos($extractedPhotos, $file->getClientOriginalName());
            $uploadedPhotos = $uploadResult['photos'];
            
            // Use first photo as profile photo
            if (!empty($uploadedPhotos) && isset($uploadedPhotos[0]['url'])) {
                $photoUrl = $uploadedPhotos[0]['url'];
            }
        }

        // Step 3: Extract structured data from text (pass photo URL)
        $extractedData = $this->extractionService->extract($text, $photoUrl);

        // Step 4: Map to form structure
        $formData = $this->formMapper->map($extractedData);

        return [
            'success' => true,
            'data' => $formData,
            'photos' => [
                'uploaded' => $uploadedPhotos,
                'total_found' => count($extractedPhotos),
                'auto_upload_enabled' => $autoUploadPhotos,
            ],
            'metadata' => [
                'parser_used' => strtolower($file->getClientOriginalExtension()),
                'file_size' => $file->getSize(),
                'original_name' => $file->getClientOriginalName(),
                'sections_found' => array_keys($extractedData['sections'] ?? []),
                'photos_in_document' => count($extractedPhotos),
                'photos_uploaded' => count($uploadedPhotos),
            ]
        ];
    }

    /**
     * Upload multiple photos
     * 
     * @param array $photos Array of photo data from parser
     * @param string $documentName Original document name for reference
     * @return array
     */
    private function uploadPhotos(array $photos, string $documentName): array
    {
        $photoService = new PhotoUploadService();
        $results = [];
        $errors = [];

        // Limit photos based on config
        $maxPhotos = config('maid_photo.max_photos_per_document', 3);
        $uploadAll = config('maid_photo.upload_all_photos', false);
        
        if (!$uploadAll) {
            $photos = array_slice($photos, 0, 1); // Only first photo
        } else {
            $photos = array_slice($photos, 0, $maxPhotos);
        }

        foreach ($photos as $index => $photo) {
            // Validate photo first
            $validation = $photoService->validateImage($photo['content']);
            
            if (!$validation['valid']) {
                $errors[] = [
                    'index' => $index,
                    'filename' => $photo['filename'],
                    'errors' => $validation['errors']
                ];
                continue;
            }

            // Convert and optimize before upload (if enabled in config)
            $content = $photo['content'];
            if (config('maid_photo.optimization.enabled', true)) {
                $content = $photoService->convertAndOptimize(
                    $photo['content'],
                    config('maid_photo.optimization.format', 'jpeg'),
                    config('maid_photo.optimization.quality', 85)
                );
            }

            // Upload
            $uploadResult = $photoService->uploadFromBinary(
                $content,
                $documentName . '_photo_' . $index
            );

            if ($uploadResult['success']) {
                $results[] = [
                    'index' => $index,
                    'original_filename' => $photo['filename'],
                    'stored_path' => $uploadResult['path'],
                    'url' => $uploadResult['url'],
                    'size' => $uploadResult['size'],
                    'mime_type' => $uploadResult['mime_type'],
                ];
            } else {
                $errors[] = [
                    'index' => $index,
                    'filename' => $photo['filename'],
                    'errors' => [$uploadResult['error']]
                ];
            }
        }

        return [
            'photos' => $results,
            'errors' => $errors,
            'success_count' => count($results),
            'error_count' => count($errors),
        ];
    }

    /**
     * Factory method to create instance with parsers and extractors
     */
    public static function create(array $parsers, array $extractors): self
    {
        $documentParser = new DocumentParser($parsers);
        $extractionService = new DataExtractionService($extractors);
        $referenceMapper = new ReferenceDataMapper();
        $formMapper = new FormDataMapper($referenceMapper);

        return new self($documentParser, $extractionService, $formMapper);
    }
}
