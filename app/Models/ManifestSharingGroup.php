<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManifestSharingGroup extends Model
{
    protected $fillable = [
        'manifest_id',
        'sharing_group_id',
        'manifest_room_id',
    ];

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(Manifest::class);
    }

    public function sharingGroup(): BelongsTo
    {
        return $this->belongsTo(SharingGroup::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ManifestRoom::class, 'manifest_room_id');
    }
}
