<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Photo Upload Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration untuk auto-upload photos dari documents
    |
    */

    // Enable/disable auto upload
    'auto_upload_enabled' => env('MAID_PHOTO_AUTO_UPLOAD', true),

    // Storage disk (local, public, s3, etc)
    'storage_disk' => env('MAID_PHOTO_STORAGE_DISK', 'public'),

    // Directory untuk menyimpan photos
    'storage_directory' => env('MAID_PHOTO_DIRECTORY', 'maids/photos'),

    // Allowed mime types
    'allowed_mime_types' => [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
    ],

    // Max file size (in bytes) - default 5MB
    'max_file_size' => env('MAID_PHOTO_MAX_SIZE', 5 * 1024 * 1024),

    // Image optimization
    'optimization' => [
        'enabled' => env('MAID_PHOTO_OPTIMIZE', true),
        'format' => env('MAID_PHOTO_FORMAT', 'jpeg'), // jpeg, png, webp
        'quality' => env('MAID_PHOTO_QUALITY', 85), // 1-100
        'max_width' => env('MAID_PHOTO_MAX_WIDTH', 1200), // Max width in pixels
        'max_height' => env('MAID_PHOTO_MAX_HEIGHT', 1600), // Max height in pixels
    ],

    // Upload semua photos atau hanya yang pertama
    'upload_all_photos' => env('MAID_PHOTO_UPLOAD_ALL', false),

    // Max number of photos to upload per document
    'max_photos_per_document' => env('MAID_PHOTO_MAX_PER_DOCUMENT', 3),
];
