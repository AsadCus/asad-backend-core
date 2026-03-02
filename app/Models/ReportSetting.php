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
            'title_color' => '#40A09D',
            'footer_text' => '',
            'show_stamp' => false,
            'show_signature' => false,
        ],
        'invoice' => [
            'title_color' => '#40A09D',
            'footer_text' => '',
            'show_stamp' => false,
            'show_signature' => false,
        ],
        'receipt' => [
            'title_color' => '#40A09D',
            'footer_text' => '',
            'show_stamp' => false,
            'show_signature' => false,
        ],
        'agreement' => [
            'title_color' => '#40A09D',
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
                'company_name' => 'Urban Care Employment Agency',
                'company_address' => "931 Yishun Central 1\n#01-109, Singapore 760931",
                'company_phone' => null,
                'company_email' => null,
                'logo_path' => null,
                'footer_text' => null,
                'stamp_path' => null,
                'signature_path' => null,
                'module_templates' => null,
            ]
        );
    }

    /**
     * Get the merged template config for a specific module type.
     * Falls back to defaults for any missing keys.
     */
    public function getModuleTemplate(string $type): array
    {
        $defaults = self::$moduleDefaults[$type] ?? [
            'title_color' => '#40A09D',
            'footer_text' => '',
            'show_stamp' => false,
            'show_signature' => false,
        ];

        $stored = $this->module_templates[$type] ?? [];

        return array_merge($defaults, $stored);
    }

    /**
     * Get the user who last updated this setting.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
