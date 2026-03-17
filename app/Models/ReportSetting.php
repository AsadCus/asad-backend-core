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
        'module_templates',
        'registered_modules',
        'updated_by',
    ];

    protected $casts = [
        'company_name' => 'string',
        'company_address' => 'string',
        'company_phone' => 'string',
        'company_email' => 'string',
        'footer_text' => 'string',
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
        ],
        'invoice' => [
            'footer_text' => '',
            'show_stamp' => false,
            'show_signature' => false,
        ],
        'receipt' => [
            'footer_text' => '',
            'show_stamp' => false,
            'show_signature' => false,
        ],
        'sales' => [
            'footer_text' => '',
            'show_stamp' => false,
            'show_signature' => false,
        ],
        'package' => [
            'footer_text' => '',
            'show_stamp' => false,
            'show_signature' => false,
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
        ];

        $stored = $this->module_templates[$type] ?? [];

        // Merge and ensure boolean casting
        $merged = array_merge($defaults, $stored);
        $merged['show_stamp'] = filter_var($merged['show_stamp'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $merged['show_signature'] = filter_var($merged['show_signature'] ?? false, FILTER_VALIDATE_BOOLEAN);

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
