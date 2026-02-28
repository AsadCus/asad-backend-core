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
        'updated_by',
    ];

    protected $casts = [
        'company_name' => 'string',
        'company_address' => 'string',
        'company_phone' => 'string',
        'company_email' => 'string',
        'footer_text' => 'string',
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
            ]
        );
    }

    /**
     * Get the user who last updated this setting.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
