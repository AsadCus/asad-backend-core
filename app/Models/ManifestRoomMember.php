<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManifestRoomMember extends Model
{
    protected $fillable = [
        'manifest_room_id',
        'manifest_member_id',
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

    public function member(): BelongsTo
    {
        return $this->belongsTo(ManifestMember::class, 'manifest_member_id');
    }
}
