<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManifestTraveler extends Model
{
    protected $fillable = [
        'manifest_id',
        'sn',
        'name_as_per_passport',
        'relationship',
        'passport_no',
        'room_no',
        'room_type',
        'bed_type',
        'date_of_birth',
        'age',
        'no_of_beds_checked',
        'meal',
        'remarks',
        'total_cost',
        'total_paid',
        'outstanding_amount',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'age' => 'integer',
        'no_of_beds_checked' => 'integer',
        'total_cost' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
    ];

    public function getDateOfBirthFormattedAttribute(): ?string
    {
        return $this->date_of_birth?->format('d/m/Y');
    }

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(Manifest::class);
    }
}
