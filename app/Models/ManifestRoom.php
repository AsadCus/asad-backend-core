<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManifestRoom extends Model
{
    protected $fillable = [
        'manifest_id',
        'sort_order',
        'location',
        'relationship',
        'room_label',
        'room_number',
        'room_type',
        'bed_type',
        'capacity',
        'sharing_plan',
        'status',
        'meal',
        'remarks',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'capacity' => 'integer',
    ];

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(Manifest::class);
    }

    public function roomMembers(): HasMany
    {
        return $this->hasMany(ManifestRoomMember::class, 'manifest_room_id')->orderBy('sort_order')->orderBy('id');
    }

    public function travelers(): BelongsToMany
    {
        return $this->belongsToMany(
            ManifestMember::class,
            'manifest_room_members',
            'manifest_room_id',
            'manifest_traveler_id',
        )->withPivot(['sort_order', 'remarks'])
            ->withTimestamps();
    }
}
