<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManifestRoom extends Model
{
    protected $fillable = [
        'manifest_id',
        'location',
        'room_number',
        'room_type',
        'bed_type',
        'capacity',
    ];

    protected $casts = [
        'capacity' => 'integer',
    ];

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(Manifest::class);
    }
}
