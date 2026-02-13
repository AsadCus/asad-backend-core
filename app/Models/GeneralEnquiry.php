<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneralEnquiry extends Model
{
    protected $fillable = [
        'enquiry_id',
        'full_name',
        'mobile',
        'email',
        'preferred_destinations',
        'preferred_travelling_date',
        'no_of_adults',
        'no_of_children',
        'requires_mobility_assistance',
    ];

    protected $casts = [
        'preferred_travelling_date' => 'date',
        'no_of_adults' => 'integer',
        'no_of_children' => 'integer',
    ];

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class, 'enquiry_id');
    }

    // Formatting Helpers
    public function getPreferredTravellingDateFormattedAttribute(): ?string
    {
        return $this->preferred_travelling_date
            ? Carbon::parse($this->preferred_travelling_date)->translatedFormat('d F Y')
            : null;
    }
}
