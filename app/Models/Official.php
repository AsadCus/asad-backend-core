<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Official extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'nationality',
        'passport_number',
        'passport_issue_date',
        'passport_expiry_date',
        'passport_place_of_issue',
        'gender',
        'date_of_birth',
        'place_of_birth',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'passport_issue_date' => 'date',
            'passport_expiry_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
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
