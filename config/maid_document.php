<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Document Generator Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for maid biodata document generation
    |
    */

    // Storage path for generated documents
    'storage_path' => 'documents/maids',

    // Template location
    'template_path' => app_path('Assets/TemplateMaid/Template Yang ingin dijadikan DOCX dan PDF.htm'),

    // PDF Settings
    'pdf' => [
        'enabled' => true,
        'default_font' => 'Arial',
        'paper_size' => 'A4',
        'orientation' => 'portrait',
        'options' => [
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'isFontSubsettingEnabled' => true,
        ],
    ],

    // DOCX Settings
    'docx' => [
        'enabled' => true,
        'default_font_name' => 'Arial',
        'default_font_size' => 10,
        'margins' => [
            'top' => 720,    // 1 inch = 720 twips
            'bottom' => 720,
            'left' => 720,
            'right' => 720,
        ],
    ],

    // File naming
    'filename' => [
        'prefix' => 'MBC',
        'include_timestamp' => true,
        'include_name' => true,
    ],

    // Default values for missing data
    'defaults' => [
        'text' => 'N/A',
        'number' => 0,
        'photo' => 'images/apple-touch-icon.png',
    ],

    // Image settings
    'images' => [
        'max_width' => 305,
        'max_height' => 400,
        'quality' => 90,
    ],
];
