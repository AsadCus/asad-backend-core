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
        'location',
        'room_number',
        'room_type',
        'bed_type',
        'capacity',
        'sharing_plan',
        'status',
        'room_label',
    ];

    protected $casts = [
        'capacity' => 'integer',
    ];

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(Manifest::class);
    }

    public function roomMembers(): HasMany
    {
        return $this->hasMany(ManifestRoomMember::class, 'manifest_room_id');
    }

    public function travelers(): BelongsToMany
    {
        return $this->belongsToMany(
            ManifestTraveler::class,
            'manifest_room_members',
            'manifest_room_id',
            'manifest_traveler_id',
        )->withPivot('role_in_room')
            ->withTimestamps();
    }

    public function manifestSharingGroups(): HasMany
    {
        return $this->hasMany(ManifestSharingGroup::class, 'manifest_room_id');
    }
}
