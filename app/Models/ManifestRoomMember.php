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
        'is_assigned',
        'remarks',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_assigned' => 'boolean',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(ManifestRoom::class, 'manifest_room_id');
    }

    public function traveler(): BelongsTo
    {
        return $this->belongsTo(ManifestMember::class, 'manifest_traveler_id');
    }
}
