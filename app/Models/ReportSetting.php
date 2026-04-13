<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSetting extends Model
{
    protected $fillable = [
        'company_name',
        'company_address',
        'company_phone',
        'company_email',
        'logo_path',
        'footer_text',
        'stamp_path',
        'signature_path',
        'brand_color',
        'signature_stamp_layout',
        'custom_stamp_path',
        'custom_signature_path',
        'qr_image_path',
        'qr_alignment',
        'custom_signature_stamp_layout',
        'module_templates',
        'registered_modules',
        'updated_by',
    ];

    protected $casts = [
        'company_name' => 'string',
        'company_address' => 'string',
        'company_phone' => 'string',
        'company_email' => 'string',
        'qr_alignment' => 'string',
        'footer_text' => 'string',
        'custom_signature_stamp_layout' => 'array',
        'module_templates' => 'array',
        'registered_modules' => 'array',
    ];

    /**
     * Default per-module template configuration.
     */
    public static array $moduleDefaults = [
        'quotation' => [
            'footer_text' => '',
            'show_stamp' => false,
            'show_signature' => false,
            'show_qr' => true,
            'show_signature_stamp_name' => false,
            'show_signature_stamp_date' => false,
        ],
        'invoice' => [
            'footer_text' => '',
            'show_stamp' => false,
            'show_signature' => false,
            'show_qr' => true,
            'show_signature_stamp_name' => false,
            'show_signature_stamp_date' => false,
        ],
        'receipt' => [
            'footer_text' => '',
            'show_stamp' => false,
            'show_signature' => false,
            'show_qr' => true,
            'show_signature_stamp_name' => false,
            'show_signature_stamp_date' => false,
        ],
        'sales' => [
            'footer_text' => '',
            'show_stamp' => false,
            'show_signature' => false,
            'show_qr' => true,
            'show_signature_stamp_name' => false,
            'show_signature_stamp_date' => false,
        ],
        'package' => [
            'footer_text' => '',
            'show_stamp' => false,
            'show_signature' => false,
            'show_qr' => true,
            'show_signature_stamp_name' => false,
            'show_signature_stamp_date' => false,
        ],
        'manifest_arabic_names' => [
            'footer_text' => '',
            'show_stamp' => false,
            'show_signature' => false,
            'show_qr' => true,
            'show_signature_stamp_name' => false,
            'show_signature_stamp_date' => false,
        ],
        'manifest_airline_names' => [
            'footer_text' => '',
            'show_stamp' => false,
            'show_signature' => false,
            'show_qr' => true,
            'show_signature_stamp_name' => false,
            'show_signature_stamp_date' => false,
        ],
        'manifest_namelist_course_items' => [
            'footer_text' => '',
            'show_stamp' => false,
            'show_signature' => false,
            'show_qr' => true,
            'show_signature_stamp_name' => false,
            'show_signature_stamp_date' => false,
        ],
        'manifest_room_check' => [
            'footer_text' => '',
            'show_stamp' => false,
            'show_signature' => false,
            'show_qr' => true,
            'show_signature_stamp_name' => false,
            'show_signature_stamp_date' => false,
        ],
        'ops_movement' => [
            'footer_text' => '',
            'show_stamp' => false,
            'show_signature' => false,
            'show_qr' => true,
            'show_signature_stamp_name' => false,
            'show_signature_stamp_date' => false,
        ],
        'ops_movement_pif' => [
            'footer_text' => '',
            'show_stamp' => false,
            'show_signature' => false,
            'show_qr' => true,
            'show_signature_stamp_name' => false,
            'show_signature_stamp_date' => false,
        ],
        'ops_movement_budget' => [
            'footer_text' => '',
            'show_stamp' => false,
            'show_signature' => false,
            'show_qr' => true,
            'show_signature_stamp_name' => false,
            'show_signature_stamp_date' => false,
        ],
        'payment_summary' => [
            'footer_text' => '',
            'show_stamp' => false,
            'show_signature' => false,
            'show_qr' => true,
            'show_signature_stamp_name' => false,
            'show_signature_stamp_date' => false,
        ],
        'customer_receipts' => [
            'footer_text' => '',
            'show_stamp' => false,
            'show_signature' => false,
            'show_qr' => true,
            'show_signature_stamp_name' => false,
            'show_signature_stamp_date' => false,
        ],
    ];

    /**
     * Get the current (singleton) report settings.
     * Creates default settings if none exist.
     */
    public static function current(): self
    {
        return self::firstOrCreate(
            ['id' => 1],
            [
                'company_name' => 'Karva Travel & Tours',
                'company_address' => '22-1 Jalan Delima 10, Wangsa Maju, 53300 Kuala Lumpur, Malaysia',
                'company_phone' => '+60 11-1608 0771',
                'company_email' => '[EMAIL_ADDRESS]',
                'logo_path' => null,
                'footer_text' => null,
                'stamp_path' => null,
                'signature_path' => null,
                'brand_color' => '#c05427',
                'signature_stamp_layout' => 'default',
                'custom_stamp_path' => null,
                'custom_signature_path' => null,
                'qr_image_path' => null,
                'qr_alignment' => 'center',
                'custom_signature_stamp_layout' => null,
                'module_templates' => null,
            ]
        );
    }

    /**
     * Get the merged template config for a specific module type.
     * Falls back to defaults for any missing keys.
     * Note: title_color is always taken from global brand_color.
     */
    public function getModuleTemplate(string $type): array
    {
        $defaults = self::$moduleDefaults[$type] ?? [
            'footer_text' => '',
            'show_stamp' => false,
            'show_signature' => false,
            'show_qr' => true,
            'show_signature_stamp_name' => false,
            'show_signature_stamp_date' => false,
        ];

        $stored = $this->module_templates[$type] ?? [];

        // Merge and ensure boolean casting
        $merged = array_merge($defaults, $stored);
        $merged['show_stamp'] = filter_var($merged['show_stamp'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $merged['show_signature'] = filter_var($merged['show_signature'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $merged['show_qr'] = filter_var($merged['show_qr'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $merged['show_signature_stamp_name'] = filter_var($merged['show_signature_stamp_name'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $merged['show_signature_stamp_date'] = filter_var($merged['show_signature_stamp_date'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Always use global brand_color for title_color
        $merged['title_color'] = $this->brand_color ?? '#c05427';

        return $merged;
    }

    /**
     * Get the user who last updated this setting.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
