<?php

namespace App\Models;

use App\Helpers\NumberGenerator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Customer extends Model
{
    private const NULLABLE_STRING_FIELDS = [
        'nric_number',
        'address',
        'nationality',
        'passport_number',
        'passport_place_of_issue',
        'gender',
        'marital_status',
        'place_of_birth',
        'chronic_disease_details',
    ];

    protected $fillable = [
        'customer_number',
        'nric_number',
        'user_id',
        'address',
        'nationality',
        'passport_number',
        'passport_issue_date',
        'passport_expiry_date',
        'passport_place_of_issue',
        'gender',
        'marital_status',
        'date_of_birth',
        'place_of_birth',
        'first_time_umrah',
        'has_chronic_disease',
        'is_using_wheelchair',
        'chronic_disease_details',
        'last_login',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'first_time_umrah' => 'boolean',
            'has_chronic_disease' => 'boolean',
            'is_using_wheelchair' => 'boolean',
            'date_of_birth' => 'date',
            'passport_issue_date' => 'date',
            'passport_expiry_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function files(): MorphMany
    {
        return $this->morphMany(ModelFile::class, 'fileable');
    }

    // Formatting Helpers
    public function getDateOfBirthFormattedAttribute(): ?string
    {
        return $this->date_of_birth
            ? Carbon::parse($this->date_of_birth)->translatedFormat('d F Y')
            : null;
    }

    public function getPassportIssueDateFormattedAttribute(): ?string
    {
        return $this->passport_issue_date
            ? Carbon::parse($this->passport_issue_date)->translatedFormat('d F Y')
            : null;
    }

    public function getPassportExpiryDateFormattedAttribute(): ?string
    {
        return $this->passport_expiry_date
            ? Carbon::parse($this->passport_expiry_date)->translatedFormat('d F Y')
            : null;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($customer) {
            if (empty($customer->customer_number)) {
                $customer->customer_number = NumberGenerator::generate('customer');
            }
        });
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, self::NULLABLE_STRING_FIELDS, true) && is_string($value)) {
            $trimmed = trim($value);
            $value = $trimmed === '' ? null : $trimmed;
        }

        return parent::setAttribute($key, $value);
    }
}
