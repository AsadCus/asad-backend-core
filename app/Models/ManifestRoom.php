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
        'source_quotation_id',
        'sort_order',
        'location',
        'group_relationship',
        'room_label',
        'room_number',
        'room_type',
        'bed_type',
        'capacity',
        'sharing_plan',
        'status',
        'meal',
        'number_of_beds_checked',
        'remarks',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'source_quotation_id' => 'integer',
        'capacity' => 'integer',
        'number_of_beds_checked' => 'boolean',
    ];

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(Manifest::class);
    }

    public function sourceQuotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'source_quotation_id');
    }

    public function roomMembers(): HasMany
    {
        return $this->hasMany(ManifestRoomMember::class, 'manifest_room_id')->orderBy('sort_order')->orderBy('id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            ManifestMember::class,
            'manifest_room_members',
            'manifest_room_id',
            'manifest_member_id',
        )->withPivot(['sort_order', 'remarks'])
            ->withTimestamps();
    }
}
