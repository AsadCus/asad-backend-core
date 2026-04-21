<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageOfficial extends Model
{
    protected $fillable = [
        'package_id',
        'type',
        'name',
        'hotel',
        'contact_number',
        'nationality',
        'passport_number',
        'gender',
        'date_of_birth',
        'passport_issue_date',
        'passport_expiry_date',
        'passport_place_of_issue',
        'place_of_birth',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'hotel' => 'array',
            'date_of_birth' => 'date',
            'passport_issue_date' => 'date',
            'passport_expiry_date' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

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
}
