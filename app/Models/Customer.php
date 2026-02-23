<?php

namespace App\Models;

use App\Helpers\NumberGenerator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Customer extends Model
{
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
        'chronic_disease_details',
        'passport_path',
        'photo_path',
        'age_preferences',
        'country_preferences',
        'experience_preferences',
        'branch_id',
        'handled_by',
        'last_login',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'first_time_umrah' => 'boolean',
            'has_chronic_disease' => 'boolean',
            'date_of_birth' => 'date',
            'passport_issue_date' => 'date',
            'passport_expiry_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by')->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function maids(): BelongsToMany
    {
        return $this->belongsToMany(Maid::class, 'customer_maid_recommendations');
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
}
