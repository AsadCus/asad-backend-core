<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\MaidManagement\MaidDocumentUploadService;
use App\Services\MaidManagement\FileParser\DocxParser;
use App\Services\MaidManagement\FileParser\PdfParser;

/**
 * Example controller untuk handle document upload dengan auto photo upload
 */
class MaidDocumentUploadController extends Controller
{
    /**
     * Upload dan process document
     * 
     * POST /api/maid/upload-document
     * 
     * Request:
     * - file: DOCX atau PDF file
     * - auto_upload_photos: boolean (optional, default true)
     */
    public function upload(Request $request)
    {
        // Validation
        $request->validate([
            'file' => 'required|file|mimes:docx,pdf|max:10240', // Max 10MB
            'auto_upload_photos' => 'boolean'
        ]);

        try {
            // Get auto upload preference from request or use config default
            $autoUploadPhotos = $request->input('auto_upload_photos', 
                config('maid_photo.auto_upload_enabled', true)
            );

            // Create service with parsers
            $parsers = [
                'docx' => new DocxParser(),
                'pdf' => new PdfParser(),
            ];

            $extractors = [
                'section' => app(\App\Services\MaidManagement\DataExtractor\SectionExtractor::class),
                'personal' => app(\App\Services\MaidManagement\DataExtractor\PersonalInformationExtractor::class),
                'medical' => app(\App\Services\MaidManagement\DataExtractor\MedicalExtractor::class),
            ];

            $service = MaidDocumentUploadService::create($parsers, $extractors);

            // Process document
            $result = $service->process($request->file('file'), $autoUploadPhotos);

            // Log success
            Log::info('Document uploaded successfully', [
                'filename' => $request->file('file')->getClientOriginalName(),
                'photos_uploaded' => count($result['photos']['uploaded'] ?? []),
                'photos_found' => $result['photos']['total_found'] ?? 0,
            ]);

            // Return response
            return response()->json([
                'success' => true,
                'message' => 'Document processed successfully',
                'data' => $result['data'],
                'photos' => [
                    'uploaded' => $result['photos']['uploaded'] ?? [],
                    'total_found' => $result['photos']['total_found'] ?? 0,
                    'total_uploaded' => count($result['photos']['uploaded'] ?? []),
                    'errors' => $result['photos']['errors'] ?? [],
                ],
                'metadata' => $result['metadata'],
            ], 200);

        } catch (\Exception $e) {
            // Log error
            Log::error('Document upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Document processing failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get photo upload configuration
     * 
     * GET /api/maid/photo-config
     */
    public function getPhotoConfig()
    {
        return response()->json([
            'success' => true,
            'config' => [
                'auto_upload_enabled' => config('maid_photo.auto_upload_enabled'),
                'max_file_size_mb' => config('maid_photo.max_file_size') / 1024 / 1024,
                'allowed_types' => config('maid_photo.allowed_mime_types'),
                'optimization_enabled' => config('maid_photo.optimization.enabled'),
                'optimization_format' => config('maid_photo.optimization.format'),
                'optimization_quality' => config('maid_photo.optimization.quality'),
                'upload_all_photos' => config('maid_photo.upload_all_photos'),
                'max_photos_per_document' => config('maid_photo.max_photos_per_document'),
            ]
        ]);
    }

    /**
     * Example: Manual photo upload (jika user ingin upload foto terpisah)
     * 
     * POST /api/maid/upload-photo
     */
    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:5120', // Max 5MB
            'name' => 'nullable|string|max:255'
        ]);

        try {
            $photoService = new \App\Services\MaidManagement\PhotoUploadService();
            
            $file = $request->file('photo');
            $content = file_get_contents($file->getRealPath());
            $name = $request->input('name', $file->getClientOriginalName());

            // Optimize before upload
            if (config('maid_photo.optimization.enabled')) {
                $content = $photoService->convertAndOptimize(
                    $content,
                    config('maid_photo.optimization.format'),
                    config('maid_photo.optimization.quality')
                );
            }

            $result = $photoService->uploadFromBinary($content, $name);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Photo upload failed',
                    'error' => $result['error']
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Photo uploaded successfully',
                'data' => [
                    'url' => $result['url'],
                    'path' => $result['path'],
                    'size' => $result['size'],
                    'mime_type' => $result['mime_type'],
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Manual photo upload failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Photo upload failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete uploaded photo
     * 
     * DELETE /api/maid/photo/{path}
     */
    public function deletePhoto(Request $request)
    {
        $request->validate([
            'path' => 'required|string'
        ]);

        try {
            $photoService = new \App\Services\MaidManagement\PhotoUploadService();
            $deleted = $photoService->delete($request->input('path'));

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Photo not found or already deleted'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Photo deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete photo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
