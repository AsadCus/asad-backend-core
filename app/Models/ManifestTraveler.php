<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManifestTraveler extends Model
{
    protected $fillable = [
        'manifest_id',
        'customer_confirmation_member_id',
        'remarks',
    ];

    protected $casts = [
        'customer_confirmation_member_id' => 'integer',
    ];

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(Manifest::class);
    }

    public function confirmationMember(): BelongsTo
    {
        return $this->belongsTo(CustomerConfirmationMember::class, 'customer_confirmation_member_id');
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
