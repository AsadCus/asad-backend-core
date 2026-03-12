<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManifestRoomMember extends Model
{
    protected $fillable = [
        'manifest_room_id',
        'manifest_traveler_id',
        'sort_order',
        'remarks',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(ManifestRoom::class, 'manifest_room_id');
    }

    public function traveler(): BelongsTo
    {
        return $this->belongsTo(ManifestTraveler::class, 'manifest_traveler_id');
    }
}
