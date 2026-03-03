<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManifestAccommodationAssignment extends Model
{
    protected $fillable = [
        'manifest_id',
        'manifest_traveler_id',
        'customer_id',
        'customer_confirmation_member_id',
        'accommodation_key',
        'sort_order',
        'sharing_group_key',
        'room_no',
        'room_type',
        'bed_type',
        'meal',
        'remarks',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(Manifest::class);
    }

    public function traveler(): BelongsTo
    {
        return $this->belongsTo(ManifestTraveler::class, 'manifest_traveler_id');
    }
}
