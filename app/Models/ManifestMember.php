<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManifestMember extends Model
{
    protected $table = 'manifest_members';

    protected $fillable = [
        'manifest_id',
        'manifest_sharing_group_id',
        'customer_confirmation_member_id',
        'sort_order',
        'remarks',
    ];

    protected $casts = [
        'manifest_sharing_group_id' => 'integer',
        'customer_confirmation_member_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(Manifest::class);
    }

    public function confirmationMember(): BelongsTo
    {
        return $this->belongsTo(CustomerConfirmationMember::class, 'customer_confirmation_member_id');
    }

    public function sharingGroup(): BelongsTo
    {
        return $this->belongsTo(ManifestSharingGroup::class, 'manifest_sharing_group_id');
    }

    public function roomMembers(): HasMany
    {
        return $this->hasMany(ManifestRoomMember::class, 'manifest_traveler_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ManifestPayment::class, 'manifest_traveler_id');
    }
}
